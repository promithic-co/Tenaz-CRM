<?php

use App\Services\WhatsApp\PhoneNumberValidator;

it('returns null for empty input', function (): void {
    expect(PhoneNumberValidator::normalize(null))->toBeNull()
        ->and(PhoneNumberValidator::normalize(''))->toBeNull()
        ->and(PhoneNumberValidator::normalize('   '))->toBeNull();
});

it('normalizes brazilian mobile with country code', function (): void {
    expect(PhoneNumberValidator::normalize('+55 (11) 91234-5678'))->toBe('5511912345678')
        ->and(PhoneNumberValidator::normalize('5511912345678'))->toBe('5511912345678')
        ->and(PhoneNumberValidator::normalize('55 11 91234 5678'))->toBe('5511912345678');
});

it('prepends 55 to brazilian mobile without country code', function (): void {
    expect(PhoneNumberValidator::normalize('11912345678'))->toBe('5511912345678')
        ->and(PhoneNumberValidator::normalize('(11) 91234-5678'))->toBe('5511912345678');
});

it('accepts brazilian landline numbers', function (): void {
    expect(PhoneNumberValidator::normalize('1132145678'))->toBe('551132145678')
        ->and(PhoneNumberValidator::normalize('551132145678'))->toBe('551132145678');
});

it('rejects brazilian mobile missing 9 prefix', function (): void {
    // 11 digits but third digit not 9 — invalid mobile under post-2012 rule
    expect(PhoneNumberValidator::normalize('11812345678'))->toBeNull();
});

it('rejects brazilian landline with invalid first digit', function (): void {
    // First digit after DDD must be 2-5 for landline
    expect(PhoneNumberValidator::normalize('1192145678'))->toBeNull()
        ->and(PhoneNumberValidator::normalize('1162145678'))->toBeNull();
});

it('rejects invalid DDD', function (): void {
    expect(PhoneNumberValidator::normalize('10912345678'))->toBeNull()
        ->and(PhoneNumberValidator::normalize('00912345678'))->toBeNull();
});

it('rejects too short numbers', function (): void {
    expect(PhoneNumberValidator::normalize('12345678'))->toBeNull()
        ->and(PhoneNumberValidator::normalize('911234567'))->toBeNull();
});

it('strips international 00 prefix', function (): void {
    expect(PhoneNumberValidator::normalize('005511912345678'))->toBe('5511912345678');
});

it('handles generic E.164 for non-BR regions', function (): void {
    // Valid 8-15 digit numbers, non-zero first digit
    expect(PhoneNumberValidator::normalize('14155551234', 'US'))->toBe('14155551234')
        ->and(PhoneNumberValidator::normalize('+44 20 7946 0958', 'GB'))->toBe('442079460958');
});

it('rejects generic E.164 starting with zero', function (): void {
    expect(PhoneNumberValidator::normalize('04155551234', 'US'))->toBeNull();
});

it('rejects generic E.164 longer than 15 digits', function (): void {
    expect(PhoneNumberValidator::normalize('1234567890123456', 'US'))->toBeNull();
});

it('strips formatting characters before validation', function (): void {
    expect(PhoneNumberValidator::normalize('(11) 9.1234-5678 — fone'))->toBe('5511912345678');
});
