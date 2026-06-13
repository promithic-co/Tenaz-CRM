<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $count = DB::table('whatsapp_instances')
            ->whereIn('provider', ['gupshup', 'waha'])
            ->count();

        if ($count > 0) {
            throw new \RuntimeException(
                "Phase 43 migration aborted — {$count} whatsapp_instance(s) still use gupshup/waha. " .
                'Delete or reassign these instances to evolution/meta_cloud before running this migration.'
            );
        }
    }

    public function down(): void
    {
        // no-op — cannot un-protect
    }
};
