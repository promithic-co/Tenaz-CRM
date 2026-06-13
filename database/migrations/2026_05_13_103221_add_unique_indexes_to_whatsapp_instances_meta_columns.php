<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detect duplicate non-null values before applying unique indexes — abort early with
        // a readable message if data is dirty, so the migration is safe to run in any env.
        $duplicates = DB::table('whatsapp_instances')
            ->select('meta_phone_number_id')
            ->whereNotNull('meta_phone_number_id')
            ->groupBy('meta_phone_number_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('meta_phone_number_id');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Duplicate meta_phone_number_id values found in whatsapp_instances: '
                .$duplicates->implode(', ').'. Resolve duplicates before re-running.'
            );
        }

        $wabaDuplicates = DB::table('whatsapp_instances')
            ->select('meta_waba_id')
            ->whereNotNull('meta_waba_id')
            ->groupBy('meta_waba_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('meta_waba_id');

        // meta_waba_id legitimately repeats across multiple phone numbers of the same WABA,
        // so we DO NOT add a unique constraint on it. Just keep the lookup index.
        if ($wabaDuplicates->isNotEmpty()) {
            \Log::info('meta_waba_id has expected duplicates (multiple phone numbers per WABA)', [
                'count' => $wabaDuplicates->count(),
            ]);
        }

        Schema::table('whatsapp_instances', function (Blueprint $table) {
            // Drop the non-unique index added by the original meta columns migration before
            // recreating it as unique — only meta_phone_number_id must be globally unique.
            $table->dropIndex('idx_whatsapp_instances_meta_phone_number_id');
            $table->unique('meta_phone_number_id', 'uniq_whatsapp_instances_meta_phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->dropUnique('uniq_whatsapp_instances_meta_phone_number_id');
            $table->index('meta_phone_number_id', 'idx_whatsapp_instances_meta_phone_number_id');
        });
    }
};
