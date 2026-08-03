<?php

use App\Services\WhatsApp\MetaHealthReasonTranslator;

beforeEach(function (): void {
    $this->translator = new MetaHealthReasonTranslator;
});

it('rewrites Meta English into something an operator can act on', function (): void {
    $translated = $this->translator->translate([
        'Your display name has not been approved yet. Your message limit will increase after the display name is approved.',
    ]);

    expect($translated)->toHaveCount(1)
        ->and($translated[0]['title'])->toBe('O nome que seus clientes veem ainda está em análise')
        ->and($translated[0]['detail'])->not->toBeNull()
        ->and($translated[0]['action'])->not->toBeNull()
        // `original` marks text we could not translate. A mapped phrase must not
        // be flagged as Meta's raw wording.
        ->and($translated[0]['original'])->toBeNull();
});

it('drops calling problems, which say nothing about sending', function (): void {
    // Seen in production on a number that was sending fine: Meta reports SIP
    // configuration on the same entity as messaging health, and it told the
    // operator to call an API endpoint they have no access to.
    $translated = $this->translator->translate([
        'WhatsApp Business calling cannot use SIP because it is not enabled Configure SIP using {PHONE_NUMBER_ID}/settings API',
    ]);

    expect($translated)->toBe([]);
});

it('keeps an unknown message instead of hiding it', function (): void {
    // Meta adds phrasings without warning. Showing English reads badly; showing
    // nothing would leave the user with a degraded status and no explanation.
    $translated = $this->translator->translate(['Some brand new Meta warning.']);

    expect($translated)->toHaveCount(1)
        ->and($translated[0]['detail'])->toBe('Some brand new Meta warning.')
        ->and($translated[0]['original'])->toBe('Some brand new Meta warning.');
});

it('reads business verification ahead of the generic restriction wording', function (): void {
    // Meta wraps the actionable cause inside a sentence that also says
    // "restricted". Matching the generic word first would bury the fix.
    $translated = $this->translator->translate([
        'Business account is restricted. Complete business verification.',
    ]);

    expect($translated[0]['title'])->toBe('Sua empresa ainda não foi verificada pela Meta');
});

it('collapses two Meta phrasings of the same problem into one line', function (): void {
    $translated = $this->translator->translate([
        'Your display name has not been approved yet.',
        'Messaging is limited until the display name is approved.',
    ]);

    expect($translated)->toHaveCount(1);
});

it('ignores blank reasons', function (): void {
    expect($this->translator->translate(['', '   ']))->toBe([]);
});

it('translates the reasons attached to each entity', function (): void {
    $entities = $this->translator->translateEntities([
        [
            'type' => 'PHONE_NUMBER',
            'id' => '1',
            'status' => 'LIMITED',
            'reasons' => [
                'Your display name has not been approved yet.',
                'WhatsApp Business calling cannot use SIP because it is not enabled',
            ],
        ],
    ]);

    expect($entities)->toHaveCount(1)
        ->and($entities[0]['type'])->toBe('PHONE_NUMBER')
        ->and($entities[0]['status'])->toBe('LIMITED')
        ->and($entities[0]['reasons'])->toHaveCount(1)
        ->and($entities[0]['reasons'][0]['title'])->toBe('O nome que seus clientes veem ainda está em análise');
});
