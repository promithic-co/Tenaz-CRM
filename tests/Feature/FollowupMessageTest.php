<?php

use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Services\AlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

describe('FollowupMessage model', function () {
    it('persists correctly when created', function () {
        $lead = Lead::factory()->create();

        $msg = FollowupMessage::create([
            'lead_id' => $lead->id,
            'attempt' => 1,
            'message_text' => 'Hello, are you still interested?',
            'tone' => 'amigavel',
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        expect($msg->id)->toBeInt();
        $this->assertDatabaseHas('followup_messages', [
            'lead_id' => $lead->id,
            'attempt' => 1,
            'tone' => 'amigavel',
            'status' => 'sent',
        ]);
    });

    it('belongs to a lead', function () {
        $lead = Lead::factory()->create();
        $msg = FollowupMessage::create([
            'lead_id' => $lead->id,
            'attempt' => 1,
            'message_text' => 'Test',
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        expect($msg->lead->id)->toBe($lead->id);
    });

    it('returns correct records via followupMessages relationship', function () {
        $lead1 = Lead::factory()->create();
        $lead2 = Lead::factory()->create();

        FollowupMessage::factory()->count(3)->create(['lead_id' => $lead1->id]);
        FollowupMessage::factory()->count(1)->create(['lead_id' => $lead2->id]);

        expect($lead1->followupMessages()->count())->toBe(3)
            ->and($lead2->followupMessages()->count())->toBe(1);
    });
});

describe('Alert rule', function () {
    it('fires alert when more than 5 ProcessLeadFollowUpJob failures in last hour', function () {
        $this->mock(AlertService::class, function ($mock) {
            $mock->shouldReceive('sendAlert')->once();
        });

        for ($i = 0; $i < 6; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => Str::uuid()->toString(),
                'connection' => 'redis',
                'queue' => 'followups',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\ProcessLeadFollowUpJob', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call']),
                'exception' => 'SomeException',
                'failed_at' => now(),
            ]);
        }

        $this->artisan('credflow:check-followups');
    });

    it('does not fire alert when 5 or fewer failures in last hour', function () {
        $this->mock(AlertService::class, function ($mock) {
            $mock->shouldReceive('sendAlert')->never();
        });

        for ($i = 0; $i < 3; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => Str::uuid()->toString(),
                'connection' => 'redis',
                'queue' => 'followups',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\ProcessLeadFollowUpJob', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call']),
                'exception' => 'SomeException',
                'failed_at' => now(),
            ]);
        }

        $this->artisan('credflow:check-followups');
    });
});

describe('Metrics queries', function () {
    it('returns correct followup metric counts', function () {
        Lead::factory()->count(3)->create(['followup_status' => 'active', 'is_sandbox' => false]);
        Lead::factory()->create(['followup_status' => 'paused', 'is_sandbox' => false]);

        $lead = Lead::factory()->create();
        FollowupMessage::factory()->count(2)->create([
            'lead_id' => $lead->id,
            'sent_at' => today(),
        ]);

        expect(Lead::where('followup_status', 'active')->count())->toBe(3)
            ->and(FollowupMessage::whereDate('sent_at', today())->count())->toBe(2);
    });
});
