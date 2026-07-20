<?php

use App\Support\Database\BuildsIndexesConcurrently;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamp each timeline message with the ConversationSession it belongs to.
 *
 * Nullable on purpose: a message write must never fail because a session could not
 * be resolved, and legacy rows stay null until the sessions:backfill command runs.
 * No FK constraint (mirrors conversation_id) so backfilling and cascade concerns stay
 * cheap; the index is built concurrently so the deploy does not lock inbound writes.
 */
return new class extends Migration
{
    use BuildsIndexesConcurrently;

    public $withinTransaction = false;

    private string $indexName = 'ctm_session_id_index';

    public function up(): void
    {
        if (! Schema::hasColumn('conversation_timeline_messages', 'session_id')) {
            Schema::table('conversation_timeline_messages', function (Blueprint $table): void {
                $table->unsignedBigInteger('session_id')->nullable()->after('lead_id');
            });
        }

        $this->createIndexConcurrently(
            'conversation_timeline_messages',
            $this->indexName,
            ['session_id'],
        );
    }

    public function down(): void
    {
        $this->dropIndexConcurrently($this->indexName);

        Schema::table('conversation_timeline_messages', function (Blueprint $table): void {
            $table->dropColumn('session_id');
        });
    }
};
