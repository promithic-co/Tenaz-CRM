<?php

use App\Services\WhatsApp\PhoneNumberValidator;

it('pairs a 9-less BR mobile with its 9th-digit form', function () {
    expect(PhoneNumberValidator::variants('556798601348'))
        ->toContain('556798601348')
        ->toContain('5567998601348');
});

it('pairs a 9th-digit BR mobile with its 9-less form', function () {
    expect(PhoneNumberValidator::variants('5567998601348'))
        ->toContain('5567998601348')
        ->toContain('556798601348');
});

it('round-trips both directions to the same pair', function () {
    $a = PhoneNumberValidator::variants('556798601348');
    $b = PhoneNumberValidator::variants('5567998601348');

    sort($a);
    sort($b);

    expect($a)->toBe($b);
});

it('keeps the raw digits even when the validator rejects the number', function () {
    // 12-digit BR mobiles fail normalize() outright — they are exactly the rows this
    // reconciles, so dropping them would defeat the purpose.
    expect(PhoneNumberValidator::normalize('556798601348'))->toBeNull()
        ->and(PhoneNumberValidator::variants('556798601348'))->toContain('556798601348');
});

it('never invents a 9th digit for a landline', function () {
    expect(PhoneNumberValidator::variants('551133334444'))
        ->toBe(['551133334444']);
});

it('strips formatting before pairing', function () {
    expect(PhoneNumberValidator::variants('+55 (67) 99860-1348'))
        ->toContain('5567998601348')
        ->toContain('556798601348');
});

it('leaves foreign numbers untouched', function () {
    expect(PhoneNumberValidator::variants('12025550143'))
        ->toBe(['12025550143']);
});

it('returns nothing for input without digits', function () {
    expect(PhoneNumberValidator::variants('sem numero'))->toBe([])
        ->and(PhoneNumberValidator::variants(null))->toBe([])
        ->and(PhoneNumberValidator::variants(''))->toBe([]);
});
