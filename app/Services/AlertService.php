<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertService
{
    public function sendAlert(string $type, string $message, array $context = []): void
    {
        if (! config('laboratory.alerting.enabled')) {
            return;
        }

        Log::channel('laboratory')->warning($type, compact('message', 'context'));

        $channel = config('laboratory.alerting.channel');

        match ($channel) {
            'slack' => $this->sendSlack($type, $message, $context),
            default => null,
        };
    }

    private function sendSlack(string $type, string $message, array $context): void
    {
        $webhook = config('laboratory.alerting.slack_webhook');

        if (! $webhook) {
            return;
        }

        try {
            Http::post($webhook, [
                'text' => "Laboratory Alert — {$type}\n{$message}",
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Laboratory Alert*\n*Type:* `{$type}`\n*Message:* {$message}",
                        ],
                    ],
                    [
                        'type' => 'context',
                        'elements' => [
                            [
                                'type' => 'mrkdwn',
                                'text' => '```'.json_encode($context, JSON_PRETTY_PRINT).'```',
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('laboratory.slack_alert_failed', ['error' => $e->getMessage()]);
        }
    }
}
