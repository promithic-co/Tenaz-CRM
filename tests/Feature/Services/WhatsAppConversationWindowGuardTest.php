<?php

use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Services\WhatsApp\WhatsAppConversationWindowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function guard(): WhatsAppConversationWindowResolver
{
    return app(WhatsAppConversationWindowResolver::class);
}

function inboundAt(Lead $lead, $at): void
{
    // created_at is not fillable, so set it after construction to control the window derivation.
    $message = new ConversationTimelineMessage([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'direction' => 'inbound',
        'sender_type' => 'lead',
        'channel' => 'whatsapp',
        'body' => 'oi',
        'status' => 'received',
        'source' => 'webhook',
    ]);
    $message->created_at = $at;
    $message->updated_at = $at;
    $message->save();
}

it('allows a free-form message while the stored window is open', function () {
    $lead = Lead::factory()->create(['service_window_expires_at' => now()->addHours(2)]);

    guard()->ensureFreeFormAllowed($lead, 'meta_cloud');
})->throwsNoExceptions();

it('blocks a free-form message when the stored window is closed', function () {
    $lead = Lead::factory()->create(['service_window_expires_at' => now()->subHour()]);

    guard()->ensureFreeFormAllowed($lead, 'meta_cloud');
})->throws(ValidationException::class);

it('derives an open window from a recent inbound when the column is null', function () {
    $lead = Lead::factory()->create(['service_window_expires_at' => null]);
    inboundAt($lead, now()->subHour());

    guard()->ensureFreeFormAllowed($lead, 'meta_cloud');
})->throwsNoExceptions();

it('blocks when the column is null and the last inbound is older than 24h', function () {
    $lead = Lead::factory()->create(['service_window_expires_at' => null]);
    inboundAt($lead, now()->subHours(30));

    guard()->ensureFreeFormAllowed($lead, 'meta_cloud');
})->throws(ValidationException::class);

it('blocks when the column is null and there is no inbound at all', function () {
    $lead = Lead::factory()->create(['service_window_expires_at' => null]);

    guard()->ensureFreeFormAllowed($lead, 'meta_cloud');
})->throws(ValidationException::class);

it('never blocks for a non-meta_cloud provider', function () {
    $lead = Lead::factory()->create(['service_window_expires_at' => now()->subHour()]);

    guard()->ensureFreeFormAllowed($lead, 'evolution');
})->throwsNoExceptions();
