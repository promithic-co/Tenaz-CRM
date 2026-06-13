<?php

use App\Events\CampaignProgressUpdated;
use App\Events\CampaignStatusChanged;
use App\Events\ConversationUpdated;
use App\Events\DashboardMetricsUpdated;
use App\Events\InstanceQualityRatingChanged;
use App\Events\LeadStatusChanged;
use App\Events\NewConversationMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

it('ConversationUpdated broadcasts on conversations.{tenantId}', function () {
    $event = new ConversationUpdated(1, '42', 'novo');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-conversations.42');
    expect($event->broadcastAs())->toBe('conversation.updated');
});

it('CampaignProgressUpdated broadcasts on campaigns.{campaignId}', function () {
    $event = new CampaignProgressUpdated(7, ['sent' => 1, 'delivered' => 0, 'failed' => 0, 'read' => 0, 'pending' => 9]);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
    expect($event->broadcastOn()[0]->name)->toBe('private-campaigns.7');
    expect($event->broadcastAs())->toBe('campaign.progress.updated');
});

it('CampaignStatusChanged broadcasts on campaigns.{campaignId}', function () {
    $event = new CampaignStatusChanged(3, 'sending');

    expect($event->broadcastOn()[0]->name)->toBe('private-campaigns.3');
    expect($event->broadcastAs())->toBe('campaign.status.changed');
});

it('InstanceQualityRatingChanged broadcasts on instances.{instanceId}', function () {
    $event = new InstanceQualityRatingChanged(5, 'GREEN');

    expect($event->broadcastOn()[0]->name)->toBe('private-instances.5');
    expect($event->broadcastAs())->toBe('instance.quality.changed');
});

it('DashboardMetricsUpdated broadcasts on dashboard.{tenantId}', function () {
    $event = new DashboardMetricsUpdated('42', ['leads' => 10]);

    expect($event->broadcastOn()[0]->name)->toBe('private-dashboard.42');
    expect($event->broadcastAs())->toBe('dashboard.metrics.updated');
});

it('LeadStatusChanged broadcasts on conversations.{tenantId}', function () {
    $event = new LeadStatusChanged(1, '42', 'novo', 'qualificado');

    expect($event->broadcastOn()[0]->name)->toBe('private-conversations.42');
    expect($event->broadcastAs())->toBe('lead.status.changed');
});

it('NewConversationMessage broadcasts now', function () {
    $event = new NewConversationMessage(1, ['role' => 'user', 'content' => 'hello', 'hora' => '10:00', 'media' => null]);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    expect($event->broadcastOn()[0]->name)->toBe('private-conversation.1');
    expect($event->broadcastAs())->toBe('message.new');
});

it('test_event_payload_does_not_include_meta_token', function () {
    $event = new InstanceQualityRatingChanged(1, 'GREEN');

    $publicProps = (new ReflectionClass($event))
        ->getProperties(ReflectionProperty::IS_PUBLIC);
    $propNames = array_map(fn ($p) => $p->getName(), $publicProps);

    expect($propNames)->not->toContain('api_key')
        ->and($propNames)->not->toContain('meta_access_token');
});
