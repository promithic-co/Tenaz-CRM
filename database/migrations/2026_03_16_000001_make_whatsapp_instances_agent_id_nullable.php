<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        // Raw SQL is safer for PostgreSQL — avoids issues with foreignId()->change() and constraints
        DB::statement('ALTER TABLE whatsapp_instances ALTER COLUMN agent_id DROP NOT NULL');
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        DB::table('whatsapp_instances')->whereNull('agent_id')->update(['agent_id' => DB::table('agents')->orderBy('id')->value('id')]);

        DB::statement('ALTER TABLE whatsapp_instances ALTER COLUMN agent_id SET NOT NULL');
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
};
