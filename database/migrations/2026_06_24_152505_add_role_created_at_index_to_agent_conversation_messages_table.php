<?php

use App\Support\Database\BuildsIndexesConcurrently;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    use BuildsIndexesConcurrently;

    public $withinTransaction = false;

    private string $table = 'agent_conversation_messages';

    private string $indexName = 'acm_role_created_idx';

    public function up(): void
    {
        $this->createIndexConcurrently($this->table, $this->indexName, ['role', 'created_at']);
    }

    public function down(): void
    {
        $this->dropIndexConcurrently($this->indexName);
    }
};
