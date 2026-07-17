<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_COLUMNS = [
        'tenant_id',
        'whatsapp_instance_id',
        'kind',
        'meta_template_name',
        'language',
    ];

    private const INDEX_NAME = 'wa_templates_canonical_meta_identity_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existingIndex = $this->canonicalIndex();

        if ($existingIndex !== null) {
            $this->assertCanonicalIndex($existingIndex);

            return;
        }

        $duplicateIdentityCount = DB::table('whatsapp_templates')
            ->select(self::INDEX_COLUMNS)
            ->whereNotNull('whatsapp_instance_id')
            ->whereNotNull('meta_template_name')
            ->groupBy(self::INDEX_COLUMNS)
            ->havingRaw('COUNT(*) > 1')
            ->limit(100)
            ->get()
            ->count();

        if ($duplicateIdentityCount > 0) {
            throw new RuntimeException(
                "Canonical Meta template identity migration aborted: {$duplicateIdentityCount} duplicate identity group(s) require reconciliation."
            );
        }

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->unique(
                self::INDEX_COLUMNS,
                self::INDEX_NAME,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existingIndex = $this->canonicalIndex();

        if ($existingIndex === null) {
            return;
        }

        $this->assertCanonicalIndex($existingIndex);

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
        });
    }

    /** @return array<string, mixed>|null */
    private function canonicalIndex(): ?array
    {
        return collect(Schema::getIndexes('whatsapp_templates'))
            ->first(fn (array $index): bool => ($index['name'] ?? null) === self::INDEX_NAME);
    }

    /** @param array<string, mixed> $index */
    private function assertCanonicalIndex(array $index): void
    {
        $isUnique = ($index['unique'] ?? false) === true;
        $columns = array_values($index['columns'] ?? []);

        if (! $isUnique || $columns !== self::INDEX_COLUMNS) {
            throw new RuntimeException(
                'Canonical Meta template index exists with an incompatible definition; migration aborted.'
            );
        }
    }
};
