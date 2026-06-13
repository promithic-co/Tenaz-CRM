<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('kind')->default('meta_hsm')->after('whatsapp_instance_id');
            $table->string('meta_template_id')->nullable()->after('kind');
            $table->string('meta_template_name')->nullable()->after('meta_template_id');
            $table->string('meta_waba_id')->nullable()->after('meta_template_name');
            $table->renameColumn('gupshup_element_name', 'element_name');

            $table->index(['tenant_id', 'kind']);
        });

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('element_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('element_name')->nullable(false)->change();
        });

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'kind']);
            $table->dropColumn(['kind', 'meta_template_id', 'meta_template_name', 'meta_waba_id']);
            $table->renameColumn('element_name', 'gupshup_element_name');
        });
    }
};
