<?php

namespace App\Jobs;

use App\Models\WhatsappInstance;
use App\Services\WhatsApp\MetaCoexistenceWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMetaCoexistenceWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $value
     */
    public function __construct(
        public readonly int $instanceId,
        public readonly string $field,
        public readonly array $value,
    ) {}

    public function handle(MetaCoexistenceWebhookService $service): void
    {
        $instance = WhatsappInstance::withoutGlobalScopes()->find($this->instanceId);

        if (! $instance || ! $instance->meta_coexistence) {
            return;
        }

        $service->process($instance, $this->field, $this->value);
    }
}
