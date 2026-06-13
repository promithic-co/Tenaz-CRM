<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly string $instance,
        public readonly string $number,
        public readonly string $text,
        public readonly ?int $instanceId = null,
    ) {
        $this->onQueue('messages');
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $whatsapp->sendText($this->instance, $this->number, $this->text);
    }
}
