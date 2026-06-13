<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{leadId}', function ($user, int $leadId) {
    $lead = Lead::withoutGlobalScope('tenant')->find($leadId);

    if (! $lead || (string) $lead->tenant_id !== (string) $user->tenantId) {
        return false;
    }

    if ($user->isRestrictedUser()) {
        return (int) $lead->assigned_user_id === (int) $user->id
            || $lead->agent?->user_id === $user->id;
    }

    return true;
});

Broadcast::channel('atendimentos.{tenantId}', function ($user, string $tenantId) {
    return (string) $user->tenantId === $tenantId;
});

Broadcast::channel('conversations.{tenantId}', function ($user, string $tenantId) {
    return (string) $user->tenantId === $tenantId;
});

Broadcast::channel('campaigns.{campaignId}', function ($user, int $campaignId) {
    $campaign = Campaign::withoutGlobalScope('tenant')->find($campaignId);

    return $campaign && (string) $campaign->tenant_id === (string) $user->tenantId;
});

Broadcast::channel('instances.{instanceId}', function ($user, int $instanceId) {
    $instance = WhatsappInstance::withoutGlobalScope('tenant')->find($instanceId);

    return $instance && (string) $instance->tenant_id === (string) $user->tenantId;
});

Broadcast::channel('dashboard.{tenantId}', function ($user, string $tenantId) {
    return (string) $user->tenantId === $tenantId;
});
