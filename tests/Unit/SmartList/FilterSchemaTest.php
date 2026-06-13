<?php

use App\Exceptions\SmartList\InvalidFiltersException;
use App\Services\SmartList\FilterSchema;

// ─── Helpers ──────────────────────────────────────────────────────────────────

if (! function_exists('validFilters')) {
    function validFilters(array $overrides = []): array
    {
        return array_replace_recursive([
            'version' => 1,
            'match' => 'all',
            'rules' => [],
        ], $overrides);
    }
}

// ─── Version & structure ──────────────────────────────────────────────────────

describe('FilterSchema::validate — version & structure', function () {
    test('valid empty rules schema does not throw', function () {
        expect(fn () => FilterSchema::validate(validFilters()))->not->toThrow(InvalidFiltersException::class);
    });

    test('missing version throws', function () {
        $filters = validFilters();
        unset($filters['version']);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class, 'version');
    });

    test('wrong version number throws', function () {
        expect(fn () => FilterSchema::validate(validFilters(['version' => 2])))
            ->toThrow(InvalidFiltersException::class, 'version');
    });

    test('invalid match value throws', function () {
        expect(fn () => FilterSchema::validate(validFilters(['match' => 'none'])))
            ->toThrow(InvalidFiltersException::class, 'match');
    });

    test('match "any" is valid', function () {
        expect(fn () => FilterSchema::validate(validFilters(['match' => 'any'])))
            ->not->toThrow(InvalidFiltersException::class);
    });

    test('missing rules key throws', function () {
        $filters = validFilters();
        unset($filters['rules']);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class, 'rules');
    });

    test('rules exceeding max count throws', function () {
        $rules = array_fill(0, FilterSchema::MAX_RULES + 1, [
            'field' => 'has_open_ticket',
            'op' => 'eq',
            'value' => true,
        ]);

        expect(fn () => FilterSchema::validate(validFilters(['rules' => $rules])))
            ->toThrow(InvalidFiltersException::class, 'maximum');
    });
});

// ─── Unknown / disallowed field & op ─────────────────────────────────────────

describe('FilterSchema::validate — field & op validation', function () {
    test('unknown field throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'nonexistent_field', 'op' => 'eq', 'value' => 'x'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class, 'nonexistent_field');
    });

    test('op "eq" on tags field throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tags', 'op' => 'eq', 'value' => ['vip']],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('op "includes_all" on tags is valid', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tags', 'op' => 'includes_all', 'value' => ['vip']],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('op "includes_any" on tags is valid', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tags', 'op' => 'includes_any', 'value' => ['vip', 'idoso']],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('op "excludes" on tags is valid', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tags', 'op' => 'excludes', 'value' => ['optou_sair']],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('missing op throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'has_open_ticket', 'value' => true],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class, 'op');
    });

    test('missing value throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'has_open_ticket', 'op' => 'eq'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class, 'value');
    });
});

// ─── Value type validation ────────────────────────────────────────────────────

describe('FilterSchema::validate — value types', function () {
    test('tags value as string (not array) throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tags', 'op' => 'includes_all', 'value' => 'vip'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('tags empty array throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tags', 'op' => 'includes_all', 'value' => []],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('tag_is_hot with string value throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tag_is_hot', 'op' => 'eq', 'value' => 'true'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('tag_is_hot with bool value is valid', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tag_is_hot', 'op' => 'eq', 'value' => true],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('tag_source with invalid value throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tag_source', 'op' => 'eq', 'value' => 'human'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('tag_source "ai" is valid', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'tag_source', 'op' => 'eq', 'value' => 'ai'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('agent_id with string value throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'agent_id', 'op' => 'eq', 'value' => '12'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('agent_id with negative int throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'agent_id', 'op' => 'eq', 'value' => -1],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('agent_id with positive int is valid', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'agent_id', 'op' => 'eq', 'value' => 12],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('last_interaction_at with positive int days is valid', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'last_interaction_at', 'op' => 'older_than_days', 'value' => 30],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('last_interaction_at with zero days throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'last_interaction_at', 'op' => 'older_than_days', 'value' => 0],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('has_open_ticket with bool true is valid', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'has_open_ticket', 'op' => 'eq', 'value' => true],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('has_open_ticket with int throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'has_open_ticket', 'op' => 'eq', 'value' => 1],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });
});

// ─── Custom field rules ───────────────────────────────────────────────────────

describe('FilterSchema::validate — custom_field rules', function () {
    test('valid custom_field rule does not throw', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'custom_field:produto', 'op' => 'eq', 'value' => 'novo'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });

    test('custom_field with invalid slug throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'custom_field:has spaces', 'op' => 'eq', 'value' => 'x'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('custom_field with empty slug throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'custom_field:', 'op' => 'eq', 'value' => 'x'],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('custom_field with unsupported op throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'custom_field:produto', 'op' => 'in', 'value' => ['novo']],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });

    test('custom_field with array value throws', function () {
        $filters = validFilters(['rules' => [
            ['field' => 'custom_field:produto', 'op' => 'eq', 'value' => ['novo']],
        ]]);

        expect(fn () => FilterSchema::validate($filters))->toThrow(InvalidFiltersException::class);
    });
});

// ─── Raw JSON size guard ──────────────────────────────────────────────────────

describe('FilterSchema::validateRaw — size guard', function () {
    test('payload exceeding max bytes throws', function () {
        $json = json_encode(validFilters(['rules' => [
            ['field' => 'has_open_ticket', 'op' => 'eq', 'value' => true],
        ]]));
        // Pad to exceed limit
        $oversized = str_repeat('a', FilterSchema::MAX_PAYLOAD_BYTES + 1);

        expect(fn () => FilterSchema::validateRaw($oversized))->toThrow(InvalidFiltersException::class);
    });

    test('valid JSON string under limit does not throw', function () {
        $json = json_encode(validFilters(['rules' => [
            ['field' => 'has_open_ticket', 'op' => 'eq', 'value' => true],
        ]]));

        expect(fn () => FilterSchema::validateRaw($json))->not->toThrow(InvalidFiltersException::class);
    });

    test('invalid JSON string throws', function () {
        expect(fn () => FilterSchema::validateRaw('{not json}'))->toThrow(InvalidFiltersException::class);
    });
});

// ─── Multi-rule valid schema ──────────────────────────────────────────────────

describe('FilterSchema::validate — complex valid schema', function () {
    test('valid schema with multiple rules does not throw', function () {
        $filters = validFilters([
            'rules' => [
                ['field' => 'tags', 'op' => 'includes_all', 'value' => ['vip', 'sem_credito']],
                ['field' => 'last_interaction_at', 'op' => 'older_than_days', 'value' => 30],
                ['field' => 'status', 'op' => 'not_in', 'value' => ['optou_sair', 'arquivado']],
                ['field' => 'tag_is_hot', 'op' => 'eq', 'value' => false],
            ],
        ]);

        expect(fn () => FilterSchema::validate($filters))->not->toThrow(InvalidFiltersException::class);
    });
});
