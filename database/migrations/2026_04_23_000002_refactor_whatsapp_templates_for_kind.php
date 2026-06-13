<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropUnique('whatsapp_templates_tenant_id_whatsapp_instance_id_gupshup_element_name_unique');

            $table->unique(
                ['tenant_id', 'whatsapp_instance_id', 'kind', 'name'],
                'wa_templates_tenant_inst_kind_name_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropUnique('wa_templates_tenant_inst_kind_name_unique');
            $table->unique(['tenant_id', 'whatsapp_instance_id', 'gupshup_element_name']);
        });
    }
};
