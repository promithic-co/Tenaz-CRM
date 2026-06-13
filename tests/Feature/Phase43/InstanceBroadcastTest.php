<?php

use App\Events\InstanceQualityRatingChanged;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Event;

it('test_meta_webhook_quality_update_dispatches_quality_rating_changed', function () {
    Event::fake([InstanceQualityRatingChanged::class]);

    $secret = 'test-app-secret-123';
    config(['services.meta.app_secret' => $secret]);

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '11111111111111',
    ]);

    $payload = [
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'changes' => [[
                'field' => 'phone_number_quality_update',
                'value' => [
                    'event' => 'FLAGGED',
                    'new_quality_score' => 'RED',
                    'metadata' => ['phone_number_id' => $instance->meta_phone_number_id],
                ],
            ]],
        ]],
    ];

    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

    $this->call('POST', '/api/webhooks/meta', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertNoContent();

    Event::assertDispatched(InstanceQualityRatingChanged::class, function (InstanceQualityRatingChanged $e) use ($instance) {
        return $e->instanceId === $instance->id
            && $e->qualityRating === 'RED';
    });
});
