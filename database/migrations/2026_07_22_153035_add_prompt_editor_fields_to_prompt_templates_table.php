<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editor state for the backoffice prompt screen. The runtime keeps reading
     * `content` alone — these columns only let the editor reopen what the
     * operator typed instead of reverse-engineering it from the composed text.
     */
    public function up(): void
    {
        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->json('sections')->nullable()->after('content');
            $table->string('editor_mode', 20)->nullable()->after('sections');
        });
    }

    public function down(): void
    {
        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->dropColumn(['sections', 'editor_mode']);
        });
    }
};
