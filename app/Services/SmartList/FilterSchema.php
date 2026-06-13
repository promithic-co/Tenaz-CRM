<?php

namespace App\Services\SmartList;

use App\Exceptions\SmartList\InvalidFiltersException;

/**
 * Canonical schema definition and validator for smart list `filters_json`.
 *
 * The filters_json AST structure:
 * {
 *   "version": 1,
 *   "match": "all" | "any",       // AND or OR at the root level across rules
 *   "rules": [
 *     {"field": "tags",                 "op": "includes_all" | "includes_any" | "excludes", "value": ["slug1"]},
 *     {"field": "tag_is_hot",           "op": "eq",                                         "value": true},
 *     {"field": "tag_source",           "op": "eq",                                         "value": "manual" | "ai"},
 *     {"field": "status",               "op": "in" | "not_in",                              "value": ["qualificado"]},
 *     {"field": "agent_id",             "op": "eq",                                         "value": 12},
 *     {"field": "whatsapp_instance_id", "op": "eq",                                         "value": 5},
 *     {"field": "last_interaction_at",  "op": "older_than_days" | "within_last_days",       "value": 30},
 *     {"field": "created_at",           "op": "older_than_days" | "within_last_days",       "value": 7},
 *     {"field": "custom_field:slug",    "op": "eq" | "ne" | "contains",                    "value": "..."},
 *     {"field": "has_open_ticket",      "op": "eq",                                         "value": true}
 *   ]
 * }
 */
final class FilterSchema
{
    public const VERSION = 1;

    /** Maximum allowed raw JSON payload size in bytes (anti-DoS). */
    public const MAX_PAYLOAD_BYTES = 10_240; // 10 KB

    /** Maximum number of rules allowed in a single filter set. */
    public const MAX_RULES = 50;

    /**
     * All fields that may appear in a rule's `field` key.
     * Custom fields use the prefix "custom_field:" followed by a slug.
     *
     * @return list<string>
     */
    public static function allowedFields(): array
    {
        return [
            'tags',
            'tag_is_hot',
            'tag_source',
            'status',
            'agent_id',
            'whatsapp_instance_id',
            'last_interaction_at',
            'created_at',
            'has_open_ticket',
            // custom_field:<slug> handled via prefix check
        ];
    }

    /**
     * Returns the valid operators for a given field.
     *
     * @return list<string>
     */
    public static function allowedOps(string $field): array
    {
        return match (true) {
            $field === 'tags' => ['includes_all', 'includes_any', 'excludes'],
            $field === 'tag_is_hot' => ['eq'],
            $field === 'tag_source' => ['eq'],
            $field === 'status' => ['in', 'not_in'],
            $field === 'agent_id' => ['eq'],
            $field === 'whatsapp_instance_id' => ['eq'],
            $field === 'last_interaction_at' => ['older_than_days', 'within_last_days'],
            $field === 'created_at' => ['older_than_days', 'within_last_days'],
            str_starts_with($field, 'custom_field:') => ['eq', 'ne', 'contains'],
            $field === 'has_open_ticket' => ['eq'],
            default => [],
        };
    }

    /**
     * Validate a filters array decoded from filters_json.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws InvalidFiltersException
     */
    public static function validate(array $filters): void
    {
        // Version check
        if (! isset($filters['version']) || $filters['version'] !== self::VERSION) {
            throw new InvalidFiltersException(
                'filters_json version mismatch. Expected version '.self::VERSION.'.'
            );
        }

        // Match check
        if (! isset($filters['match']) || ! in_array($filters['match'], ['all', 'any'], true)) {
            throw new InvalidFiltersException(
                'filters_json.match must be "all" or "any".'
            );
        }

        // Rules presence
        if (! isset($filters['rules']) || ! is_array($filters['rules'])) {
            throw new InvalidFiltersException(
                'filters_json.rules must be an array.'
            );
        }

        if (count($filters['rules']) > self::MAX_RULES) {
            throw new InvalidFiltersException(
                'filters_json.rules exceeds the maximum of '.self::MAX_RULES.' rules.'
            );
        }

        foreach ($filters['rules'] as $index => $rule) {
            self::validateRule($rule, $index);
        }
    }

    /**
     * Validate a decoded filters array from a raw JSON string, including size guard.
     *
     * @throws InvalidFiltersException
     */
    public static function validateRaw(string $json): void
    {
        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            throw new InvalidFiltersException(
                'filters_json payload exceeds '.self::MAX_PAYLOAD_BYTES.' bytes. Add more filters to reduce scope.'
            );
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidFiltersException('filters_json must be a valid JSON object.');
        }

        self::validate($decoded);
    }

    /**
     * @param  mixed  $rule
     *
     * @throws InvalidFiltersException
     */
    private static function validateRule(mixed $rule, int $index): void
    {
        if (! is_array($rule)) {
            throw new InvalidFiltersException("rules[$index] must be an object.");
        }

        if (! isset($rule['field']) || ! is_string($rule['field'])) {
            throw new InvalidFiltersException("rules[$index].field is required and must be a string.");
        }

        $field = $rule['field'];

        // Check field is allowed (exact match or custom_field: prefix)
        if (! in_array($field, self::allowedFields(), true) && ! str_starts_with($field, 'custom_field:')) {
            throw new InvalidFiltersException(
                "rules[$index].field \"$field\" is not a recognized filter field."
            );
        }

        // Validate custom_field slug
        if (str_starts_with($field, 'custom_field:')) {
            $slug = substr($field, strlen('custom_field:'));
            if (empty($slug) || ! preg_match('/^[a-z0-9_]+$/', $slug)) {
                throw new InvalidFiltersException(
                    "rules[$index].field custom_field slug must be a non-empty lowercase alphanumeric/underscore string."
                );
            }
        }

        if (! isset($rule['op']) || ! is_string($rule['op'])) {
            throw new InvalidFiltersException("rules[$index].op is required and must be a string.");
        }

        $op = $rule['op'];
        $allowedOps = self::allowedOps($field);

        if (! in_array($op, $allowedOps, true)) {
            $allowed = implode(', ', $allowedOps);
            throw new InvalidFiltersException(
                "rules[$index].op \"$op\" is not valid for field \"$field\". Allowed: $allowed."
            );
        }

        if (! array_key_exists('value', $rule)) {
            throw new InvalidFiltersException("rules[$index].value is required.");
        }

        self::validateValue($field, $op, $rule['value'], $index);
    }

    /**
     * Validate that the rule value matches the expected type for the field/op combination.
     *
     * @param  mixed  $value
     *
     * @throws InvalidFiltersException
     */
    private static function validateValue(string $field, string $op, mixed $value, int $index): void
    {
        match (true) {
            // tags: value must be a non-empty array of strings
            $field === 'tags' => self::assertNonEmptyStringArray($value, $index, 'tags value must be a non-empty array of tag slugs.'),

            // tag_is_hot: value must be bool
            $field === 'tag_is_hot' => self::assertBool($value, $index, 'tag_is_hot value must be a boolean.'),

            // tag_source: value must be one of the TaggableSource enum values
            $field === 'tag_source' => self::assertOneOf($value, ['manual', 'ai', 'import', 'system'], $index, 'tag_source value must be "manual", "ai", "import", or "system".'),

            // status: value must be non-empty array of strings
            $field === 'status' => self::assertNonEmptyStringArray($value, $index, 'status value must be a non-empty array of status slugs.'),

            // agent_id / whatsapp_instance_id: value must be a positive integer
            in_array($field, ['agent_id', 'whatsapp_instance_id'], true) => self::assertPositiveInt($value, $index, "$field value must be a positive integer."),

            // date fields: value must be a positive integer (number of days)
            in_array($field, ['last_interaction_at', 'created_at'], true) => self::assertPositiveInt($value, $index, "$field value must be a positive integer representing days."),

            // custom_field: value must be scalar (string or numeric)
            str_starts_with($field, 'custom_field:') => self::assertScalar($value, $index, 'custom_field value must be a scalar (string or number).'),

            // has_open_ticket: value must be bool
            $field === 'has_open_ticket' => self::assertBool($value, $index, 'has_open_ticket value must be a boolean.'),

            default => null, // field validation already caught unknown fields above
        };
    }

    /** @throws InvalidFiltersException */
    private static function assertNonEmptyStringArray(mixed $value, int $index, string $message): void
    {
        if (! is_array($value) || empty($value)) {
            throw new InvalidFiltersException("rules[$index]: $message");
        }

        foreach ($value as $item) {
            if (! is_string($item) || empty($item)) {
                throw new InvalidFiltersException("rules[$index]: $message");
            }
        }
    }

    /** @throws InvalidFiltersException */
    private static function assertBool(mixed $value, int $index, string $message): void
    {
        if (! is_bool($value)) {
            throw new InvalidFiltersException("rules[$index]: $message");
        }
    }

    /** @throws InvalidFiltersException */
    private static function assertOneOf(mixed $value, array $allowed, int $index, string $message): void
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new InvalidFiltersException("rules[$index]: $message");
        }
    }

    /** @throws InvalidFiltersException */
    private static function assertPositiveInt(mixed $value, int $index, string $message): void
    {
        if (! is_int($value) || $value <= 0) {
            throw new InvalidFiltersException("rules[$index]: $message");
        }
    }

    /** @throws InvalidFiltersException */
    private static function assertScalar(mixed $value, int $index, string $message): void
    {
        if (! is_scalar($value)) {
            throw new InvalidFiltersException("rules[$index]: $message");
        }
    }
}
