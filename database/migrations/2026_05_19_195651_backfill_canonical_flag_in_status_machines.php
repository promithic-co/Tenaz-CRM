<?php

use App\Models\StatusMachine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill `is_canonical` and `position` fields into the `statuses` JSON array
     * for all existing StatusMachine rows.
     *
     * This migration does NOT alter the schema — the column type remains JSON.
     * It only adds new keys to the objects inside the JSON arrays.
     *
     * Idempotent: re-running will not overwrite flags already set to true,
     * nor reset positions that are already assigned.
     */
    public function up(): void
    {
        $canonicalSlugs = StatusMachine::CANONICAL_SLUGS;

        DB::table('status_machines')->lazyById()->each(function (object $row) use ($canonicalSlugs): void {
            $statuses = json_decode($row->statuses, true);

            if (! is_array($statuses)) {
                return;
            }

            $modified = false;

            foreach ($statuses as $index => &$status) {
                $slug = $status['slug'] ?? '';
                $isCanonical = in_array($slug, $canonicalSlugs, true);

                // Only set is_canonical when not already present to avoid overwriting user changes
                if (! array_key_exists('is_canonical', $status)) {
                    $status['is_canonical'] = $isCanonical;
                    $modified = true;
                }

                // Only set position when not already present
                if (! array_key_exists('position', $status)) {
                    $status['position'] = $index;
                    $modified = true;
                }
            }
            unset($status);

            if ($modified) {
                DB::table('status_machines')
                    ->where('id', $row->id)
                    ->update(['statuses' => json_encode($statuses)]);
            }
        });
    }

    /**
     * Rollback is a no-op — we cannot safely remove keys from JSON blobs
     * because the tenant may have added custom statuses. Removing keys
     * would silently break the pipeline UI. The added keys are backwards-
     * compatible (ignored by the old code that does not read them).
     */
    public function down(): void
    {
        // Intentionally empty — non-destructive rollback.
    }
};
