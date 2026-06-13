<?php

use App\Enums\TaggableSource;

describe('TaggableSource enum', function () {
    test('defines the four expected cases with stable string values', function () {
        expect(TaggableSource::Manual->value)->toBe('manual')
            ->and(TaggableSource::Ai->value)->toBe('ai')
            ->and(TaggableSource::Import->value)->toBe('import')
            ->and(TaggableSource::System->value)->toBe('system');

        expect(TaggableSource::cases())->toHaveCount(4);
    });

    test('default() returns Manual', function () {
        expect(TaggableSource::default())->toBe(TaggableSource::Manual);
    });

    test('TaggableSource::from rejects unknown values', function () {
        TaggableSource::from('xxx');
    })->throws(ValueError::class);
});
