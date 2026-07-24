<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-agent allow-list of native tools (App\Enums\AgentToolCapability).
 *
 * Nullable and null by default on purpose: null means "no restriction set", so
 * every existing agent keeps its full toolset until an operator saves a
 * selection in the backoffice. An empty array is a real choice — no native tool.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('agent_configs', 'tool_capabilities')) {
            return;
        }

        Schema::table('agent_configs', function (Blueprint $table): void {
            $table->json('tool_capabilities')->nullable()->after('template_slug');
        });
    }

    public function down(): void
    {
        Schema::table('agent_configs', function (Blueprint $table): void {
            $table->dropColumn('tool_capabilities');
        });
    }
};
