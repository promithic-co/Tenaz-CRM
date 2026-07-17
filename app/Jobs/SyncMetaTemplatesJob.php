<?php

namespace App\Jobs;

use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use UnexpectedValueException;

class SyncMetaTemplatesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $instanceId)
    {
        $this->onQueue('default');
    }

    public function retryUntil(): ?\DateTimeInterface
    {
        $windowSeconds = (int) config('credflow.jobs.template_sync_retry_window_seconds', 3600);

        return $windowSeconds > 0 ? now()->addSeconds($windowSeconds) : null;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("meta_template_sync_instance:{$this->instanceId}"))
                ->expireAfter(180)
                ->releaseAfter(90),
        ];
    }

    public function handle(): void
    {
        $instance = WhatsappInstance::withoutGlobalScope('tenant')->find($this->instanceId);

        if (! $instance || $instance->provider->value !== 'meta_cloud') {
            return;
        }

        if ($instance->hasExpiredMetaToken()) {
            Log::warning('SyncMetaTemplatesJob: Meta token expired', ['instance_id' => $instance->id]);

            return;
        }

        $version = config('services.meta.graph_api_version', 'v23.0');
        $url = "https://graph.facebook.com/{$version}/{$instance->meta_waba_id}/message_templates?limit=100";
        $token = $instance->meta_access_token;
        $cycleSyncedAt = now();

        while ($url) {
            $response = Http::withToken($token)->timeout(60)->get($url);

            if ($response->status() === 401 || $response->status() === 403) {
                Log::warning('SyncMetaTemplatesJob: Meta token rejected', ['instance_id' => $instance->id]);

                return;
            }

            if ($response->status() === 429) {
                $this->release($this->retryAfterSeconds($response->header('Retry-After')));

                return;
            }

            if (! $response->successful()) {
                break;
            }

            $this->persistSuccessfulPage(
                $instance,
                $response->json('data', []),
                $cycleSyncedAt,
            );

            $url = $response->json('paging.next');
        }
    }

    /**
     * @param  array<int, mixed>  $providerTemplates
     */
    private function persistSuccessfulPage(
        WhatsappInstance $instance,
        array $providerTemplates,
        CarbonInterface $cycleSyncedAt,
    ): void {
        $rows = collect($providerTemplates)
            ->map(fn (mixed $template): array => $this->templateRow($instance, $template, $cycleSyncedAt))
            ->unique(fn (array $row): string => implode('|', [
                $row['tenant_id'],
                $row['whatsapp_instance_id'],
                $row['kind'],
                $row['meta_template_name'],
                $row['language'],
            ]))
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        $templateIds = DB::transaction(function () use ($rows, $instance): array {
            return array_values(
                array_filter(
                    array_map(
                        fn (array $row): ?int => $this->persistTemplateRow($row, $instance->id),
                        $rows,
                    ),
                    fn (?int $templateId): bool => $templateId !== null,
                ),
            );
        });

        foreach ($templateIds as $templateId) {
            Cache::forget("whatsapp_send_template:{$templateId}");
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistTemplateRow(array $row, int $instanceId): ?int
    {
        $identity = [
            'tenant_id' => $row['tenant_id'],
            'whatsapp_instance_id' => $row['whatsapp_instance_id'],
            'kind' => $row['kind'],
            'meta_template_name' => $row['meta_template_name'],
            'language' => $row['language'],
        ];
        $canonicalTemplateId = $this->canonicalTemplateId($identity);

        if ($canonicalTemplateId !== null) {
            $wasUpdated = $this->updateCanonicalTemplate($canonicalTemplateId, $identity, $row);

            return $wasUpdated ? $canonicalTemplateId : null;
        }

        try {
            return DB::transaction(
                fn (): int => (int) DB::table('whatsapp_templates')->insertGetId($row),
            );
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $canonicalTemplateId = $this->canonicalTemplateId($identity);

            if ($canonicalTemplateId === null) {
                throw new RuntimeException(
                    "Meta template sync identity conflict for instance {$instanceId}; synchronization aborted.",
                    previous: $exception,
                );
            }

            $wasUpdated = $this->updateCanonicalTemplate($canonicalTemplateId, $identity, $row);

            return $wasUpdated ? $canonicalTemplateId : null;
        }
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function canonicalTemplateId(array $identity): ?int
    {
        $templateId = DB::table('whatsapp_templates')
            ->where($identity)
            ->lockForUpdate()
            ->value('id');

        return $templateId === null ? null : (int) $templateId;
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $row
     */
    private function updateCanonicalTemplate(int $templateId, array $identity, array $row): bool
    {
        $affectedRows = DB::table('whatsapp_templates')
            ->where('id', $templateId)
            ->where($identity)
            ->where(function ($query) use ($row): void {
                $query
                    ->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', $row['last_synced_at']);
            })
            ->update([
                'meta_template_id' => $row['meta_template_id'],
                'meta_waba_id' => $row['meta_waba_id'],
                'status' => $row['status'],
                'category' => $row['category'],
                'body' => $row['body'],
                'header' => $row['header'],
                'footer' => $row['footer'],
                'buttons_json' => $row['buttons_json'],
                'components_json' => $row['components_json'],
                'quality_score' => $row['quality_score'],
                'rejected_reason' => $row['rejected_reason'],
                'variables_count' => $row['variables_count'],
                'last_synced_at' => $row['last_synced_at'],
                'updated_at' => $row['updated_at'],
            ]);

        return $affectedRows > 0;
    }

    /** @return array<string, mixed> */
    private function templateRow(
        WhatsappInstance $instance,
        mixed $template,
        CarbonInterface $cycleSyncedAt,
    ): array {
        if (! is_array($template)) {
            throw new UnexpectedValueException('Meta template sync page contains a non-object template.');
        }

        foreach (['id', 'name', 'status', 'language'] as $requiredField) {
            if (! is_scalar($template[$requiredField] ?? null) || trim((string) $template[$requiredField]) === '') {
                throw new UnexpectedValueException(
                    "Meta template sync page contains an invalid {$requiredField} field."
                );
            }
        }

        $componentsData = $template['components'] ?? [];
        if (! is_array($componentsData)) {
            throw new UnexpectedValueException('Meta template sync page contains invalid components.');
        }

        $components = collect($componentsData);
        $body = $components->first(fn (mixed $component): bool => is_array($component)
            && ($component['type'] ?? null) === 'BODY');
        $header = $components->first(fn (mixed $component): bool => is_array($component)
            && ($component['type'] ?? null) === 'HEADER'
            && ($component['format'] ?? null) === 'TEXT');
        $footer = $components->first(fn (mixed $component): bool => is_array($component)
            && ($component['type'] ?? null) === 'FOOTER');
        $buttonsComponent = $components->first(fn (mixed $component): bool => is_array($component)
            && ($component['type'] ?? null) === 'BUTTONS');
        $buttons = is_array($buttonsComponent) ? ($buttonsComponent['buttons'] ?? null) : null;
        $qualityScore = is_array($template['quality_score'] ?? null)
            ? ($template['quality_score']['score'] ?? null)
            : ($template['quality_score'] ?? null);

        return [
            'tenant_id' => $instance->tenant_id,
            'whatsapp_instance_id' => $instance->id,
            'kind' => 'meta_hsm',
            'meta_template_id' => (string) $template['id'],
            'meta_template_name' => (string) $template['name'],
            'meta_waba_id' => $instance->meta_waba_id,
            'name' => (string) $template['name'],
            'status' => (string) $template['status'],
            'category' => is_scalar($template['category'] ?? null) ? (string) $template['category'] : null,
            'language' => (string) $template['language'],
            'body' => is_array($body) && is_scalar($body['text'] ?? null) ? (string) $body['text'] : '',
            'header' => is_array($header) && is_scalar($header['text'] ?? null) ? (string) $header['text'] : null,
            'footer' => is_array($footer) && is_scalar($footer['text'] ?? null) ? (string) $footer['text'] : null,
            'buttons_json' => is_array($buttons) && $buttons !== []
                ? json_encode($buttons, JSON_THROW_ON_ERROR)
                : null,
            'components_json' => json_encode($componentsData, JSON_THROW_ON_ERROR),
            'quality_score' => is_scalar($qualityScore) ? (string) $qualityScore : null,
            'rejected_reason' => is_scalar($template['rejected_reason'] ?? $template['reason'] ?? null)
                ? (string) ($template['rejected_reason'] ?? $template['reason'])
                : null,
            'variables_count' => $this->countVars($componentsData),
            'last_synced_at' => $cycleSyncedAt,
            'created_at' => $cycleSyncedAt,
            'updated_at' => $cycleSyncedAt,
        ];
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['19', '1062'], true);
    }

    /**
     * @param  array<int, mixed>|string  $components
     */
    private function countVars(array|string $components): int
    {
        $text = is_array($components) ? $this->extractTemplateText($components) : $components;

        return WhatsappTemplate::countVariablesIn($text);
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function extractTemplateText(array $value): string
    {
        $parts = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $parts[] = $item;
            } elseif (is_array($item)) {
                $parts[] = $this->extractTemplateText($item);
            }
        }

        return implode(' ', $parts);
    }

    private function retryAfterSeconds(?string $header): int
    {
        $fallback = 60;
        $max = max(1, (int) config('credflow.jobs.template_sync_max_retry_after_seconds', 3600));

        if ($header === null || trim($header) === '') {
            return min($fallback, $max);
        }

        $header = trim($header);

        if (ctype_digit($header)) {
            return min(max(1, (int) $header), $max);
        }

        $retryAt = \DateTimeImmutable::createFromFormat(DATE_RFC7231, $header);
        if ($retryAt === false) {
            return min($fallback, $max);
        }

        return min(max(1, $retryAt->getTimestamp() - now()->timestamp), $max);
    }
}
