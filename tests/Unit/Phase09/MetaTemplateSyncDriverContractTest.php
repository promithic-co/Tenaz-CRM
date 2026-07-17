<?php

it('does not rely on MySQL upsert conflict target semantics', function () {
    $source = file_get_contents(app_path('Jobs/SyncMetaTemplatesJob.php'));

    expect($source)->not->toBeFalse()
        ->and($source)->not->toContain('->upsert(')
        ->and($source)->toContain('insertGetId(')
        ->and($source)->toContain('canonicalTemplateId(')
        ->and($source)->toContain('->lockForUpdate()')
        ->and($source)->toContain('isUniqueConstraintViolation(');

    expect(substr_count($source, 'DB::transaction('))->toBeGreaterThanOrEqual(2);
});
