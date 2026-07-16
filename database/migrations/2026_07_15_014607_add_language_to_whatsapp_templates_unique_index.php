<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Meta HSM template exists once per (name, language) on Meta's side: the same template
     * name is returned as a separate entry per language. The prior unique key omitted language,
     * so syncing a multi-language template forced every language variant onto one row (flapping),
     * and keyed the sync on the internal `name` rather than the Meta identity. Adding `language`
     * to the unique index lets each language variant own its row while still forbidding two
     * templates from sharing the same internal name within one language.
     */
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropUnique('wa_templates_tenant_inst_kind_name_unique');

            $table->unique(
                ['tenant_id', 'whatsapp_instance_id', 'kind', 'name', 'language'],
                'wa_templates_tenant_inst_kind_name_lang_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropUnique('wa_templates_tenant_inst_kind_name_lang_unique');

            $table->unique(
                ['tenant_id', 'whatsapp_instance_id', 'kind', 'name'],
                'wa_templates_tenant_inst_kind_name_unique'
            );
        });
    }
};
