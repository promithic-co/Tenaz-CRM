<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive-only: turns niche_templates into the agent template registry
     * (marketplace cards + creation variables) without touching existing rows.
     */
    public function up(): void
    {
        Schema::table('niche_templates', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('category')->nullable()->after('description');
            $table->string('mode')->nullable()->after('category');
            $table->string('icon')->nullable()->after('mode');
            $table->string('tagline')->nullable()->after('icon');
            $table->json('use_cases')->nullable()->after('tagline');
            $table->string('example_first_message', 500)->nullable()->after('use_cases');
            $table->json('variables_schema')->nullable()->after('default_config');
            $table->string('agent_class')->nullable()->after('variables_schema');
            $table->string('visibility')->default('system')->after('agent_class');
            $table->string('origin_tenant_id')->nullable()->after('visibility');
            $table->boolean('is_active')->default(true)->after('origin_tenant_id');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('niche_templates', function (Blueprint $table) {
            $table->dropColumn([
                'label',
                'category',
                'mode',
                'icon',
                'tagline',
                'use_cases',
                'example_first_message',
                'variables_schema',
                'agent_class',
                'visibility',
                'origin_tenant_id',
                'is_active',
                'sort_order',
            ]);
        });
    }
};
