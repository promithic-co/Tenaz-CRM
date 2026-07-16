<?php

namespace App\Jobs;

use App\Models\WhatsappInstance;
use App\Services\WhatsApp\MetaTokenExchangeService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SyncMetaCoexistenceDataJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 180];

    public function __construct(public readonly int $instanceId) {}

    public function uniqueId(): string
    {
        return (string) $this->instanceId;
    }

    public function handle(MetaTokenExchangeService $tokenService): void
    {
        $instance = WhatsappInstance::withoutGlobalScopes()->find($this->instanceId);

        if (! $instance || ! $instance->meta_coexistence) {
            return;
        }

        $phoneNumberId = (string) $instance->meta_phone_number_id;
        $accessToken = (string) $instance->meta_access_token;

        if ($phoneNumberId === '' || $accessToken === '') {
            throw new RuntimeException('Coexistence instance is missing Meta credentials.');
        }

        foreach (['smb_app_state_sync', 'history'] as $syncType) {
            $completedKey = "meta_coexistence_sync:{$instance->id}:{$syncType}";

            if (Cache::has($completedKey)) {
                continue;
            }

            if (! $tokenService->requestAppDataSync($phoneNumberId, $accessToken, $syncType)) {
                throw new RuntimeException("Meta rejected the {$syncType} synchronization request.");
            }

            Cache::forever($completedKey, now()->toIso8601String());
        }
    }
}
