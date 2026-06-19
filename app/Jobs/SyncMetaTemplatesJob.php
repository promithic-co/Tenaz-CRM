<?php

namespace App\Jobs;

use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncMetaTemplatesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $instanceId)
    {
        $this->onQueue('default');
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

        while ($url) {
            $response = Http::withToken($token)->timeout(60)->get($url);

            if ($response->status() === 401 || $response->status() === 403) {
                Log::warning('SyncMetaTemplatesJob: Meta token rejected', ['instance_id' => $instance->id]);

                return;
            }

            if ($response->status() === 429) {
                $this->release(60);

                return;
            }

            if (! $response->successful()) {
                break;
            }

            foreach ($response->json('data', []) as $tpl) {
                $components = collect($tpl['components'] ?? []);
                $body = $components->firstWhere('type', 'BODY')['text'] ?? '';
                $header = $components->first(fn ($c): bool => ($c['type'] ?? null) === 'HEADER' && ($c['format'] ?? null) === 'TEXT');
                $footer = $components->firstWhere('type', 'FOOTER');
                $buttons = $components->firstWhere('type', 'BUTTONS')['buttons'] ?? null;
                $qualityScore = $tpl['quality_score']['score'] ?? $tpl['quality_score'] ?? null;

                WhatsappTemplate::updateOrCreate(
                    [
                        'tenant_id' => $instance->tenant_id,
                        'whatsapp_instance_id' => $instance->id,
                        'kind' => 'meta_hsm',
                        'name' => $tpl['name'],
                    ],
                    [
                        'meta_template_id' => $tpl['id'],
                        'meta_template_name' => $tpl['name'],
                        'meta_waba_id' => $instance->meta_waba_id,
                        'status' => $tpl['status'],
                        'category' => $tpl['category'] ?? null,
                        'language' => $tpl['language'],
                        'body' => $body,
                        'header' => $header['text'] ?? null,
                        'footer' => $footer['text'] ?? null,
                        'buttons_json' => is_array($buttons) && $buttons !== [] ? $buttons : null,
                        'components_json' => $components->all(),
                        'quality_score' => is_scalar($qualityScore) ? (string) $qualityScore : null,
                        'rejected_reason' => $tpl['rejected_reason'] ?? $tpl['reason'] ?? null,
                        'variables_count' => $this->countVars($components->all()),
                    ]
                );
            }

            $url = $response->json('paging.next');
        }
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
}
