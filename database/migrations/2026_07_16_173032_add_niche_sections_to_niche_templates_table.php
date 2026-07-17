<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Niche layer for composed prompts: ordered list of {title, content} sections
     * rendered by PromptComposer between the platform core sections.
     * Kept separate from prompt_templates (fully-authored standalone prompts).
     */
    public function up(): void
    {
        Schema::table('niche_templates', function (Blueprint $table) {
            $table->json('niche_sections')->nullable()->after('variables_schema');
        });
    }

    public function down(): void
    {
        Schema::table('niche_templates', function (Blueprint $table) {
            $table->dropColumn('niche_sections');
        });
    }
};
