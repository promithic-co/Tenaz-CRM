<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 52 — ConversationSession (Atendimento).
 *
 * A Lead is the per-phone aggregate (one row per tenant+whatsapp, forever). A
 * ConversationSession is one service cycle inside that lead: it opens on the first
 * inbound, on re-engagement after a terminal status, after a long inactivity gap,
 * or from a campaign/manual action, and closes when the lead reaches a terminal
 * status, a ticket is finalised, or it is auto-closed. This lets the funnel and
 * metrics be scoped per atendimento instead of per lead-lifetime.
 *
 * The single-open-per-lead invariant is enforced by a partial unique index added
 * in the companion migration (Postgres/SQLite partial index; the app also serialises
 * opens behind a cache lock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number')->default(1);
            $table->string('status', 10)->default('open');
            $table->string('open_reason', 40);
            $table->string('outcome', 30)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'opened_at']);
            $table->index(['lead_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_sessions');
    }
};
