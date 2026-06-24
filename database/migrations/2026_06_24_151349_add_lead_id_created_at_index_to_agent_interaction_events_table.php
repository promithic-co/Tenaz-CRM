<?php

use App\Support\Database\BuildsIndexesConcurrently;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    use BuildsIndexesConcurrently;

    public $withinTransaction = false;

    private string $table = 'agent_interaction_events';

    private string $indexName = 'agent_interaction_events_lead_time_idx';

    public function up(): void
    {
        $this->createIndexConcurrently($this->table, $this->indexName, ['lead_id', 'created_at']);
    }

    public function down(): void
    {
        $this->dropIndexConcurrently($this->indexName);
    }
};
