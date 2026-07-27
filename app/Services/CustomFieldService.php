<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Per-tenant extra fields on leads: the definitions an administrator manages and
 * the values an operator fills in from the conversation panel.
 *
 * The schema already supported this (custom_fields + custom_field_values, one
 * column per type); this service is the seam that both the settings CRUD and the
 * panel go through, so type coercion lives in exactly one place.
 */
class CustomFieldService
{
    /**
     * Only lead fields are exposed today. The tables are polymorphic, so the
     * constant keeps a future contact/campaign field out of the lead UI instead
     * of relying on every query to remember the filter.
     */
    public const ENTITY_LEAD = 'lead';

    /**
     * Types an administrator may create, in the order the picker offers them.
     */
    public const TYPES = ['text', 'number', 'date', 'boolean', 'select'];

    /**
     * `json` fields are written by agent tools and niche templates (INSS ships
     * `credito` and `documentos`), never typed by hand. They stay visible in the
     * panel as read-only context.
     */
    public const READ_ONLY_TYPES = ['json'];

    /**
     * A cap, not a limit anyone should hit: the panel section is a sidebar, and
     * thirty fields already scroll past a laptop screen.
     */
    public const MAX_FIELDS = 30;

    private const MAX_TEXT_LENGTH = 2000;

    /** @return Collection<int, CustomField> */
    public function definitionsForTenant(string $tenantId): Collection
    {
        return CustomField::forTenant($tenantId)
            ->forEntity(self::ENTITY_LEAD)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Definitions plus how many leads already carry a value, so the settings page
     * can warn before a delete throws data away.
     *
     * @return list<array{id: int, slug: string, label: string, type: string, options: list<array{value: string, label: string}>, is_required: bool, sort_order: int, editable: bool, values_count: int}>
     */
    public function definitionsPayload(string $tenantId): array
    {
        $definitions = $this->definitionsForTenant($tenantId);

        $counts = CustomFieldValue::query()
            ->whereIn('custom_field_id', $definitions->pluck('id'))
            ->where('entity_type', self::ENTITY_LEAD)
            ->selectRaw('custom_field_id, count(*) as aggregate')
            ->groupBy('custom_field_id')
            ->pluck('aggregate', 'custom_field_id');

        return $definitions
            ->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'slug' => $field->slug,
                'label' => $field->label,
                'type' => $field->type,
                'options' => $this->normalizeOptions($field->options),
                'is_required' => (bool) $field->is_required,
                'sort_order' => (int) $field->sort_order,
                'editable' => $this->isEditable($field->type),
                'values_count' => (int) ($counts[$field->id] ?? 0),
            ])
            ->all();
    }

    /**
     * @param  array{label: string, type: string, options?: list<array{value: string, label: string}>|null, is_required?: bool}  $data
     *
     * @throws ValidationException
     */
    public function create(string $tenantId, array $data): CustomField
    {
        $existing = $this->definitionsForTenant($tenantId);

        if ($existing->count() >= self::MAX_FIELDS) {
            throw ValidationException::withMessages([
                'label' => 'Limite de '.self::MAX_FIELDS.' campos adicionais atingido.',
            ]);
        }

        $slug = $this->slugFrom($data['label']);

        if ($existing->contains(fn (CustomField $field): bool => $field->slug === $slug)) {
            throw ValidationException::withMessages([
                'label' => 'Já existe um campo com esse nome.',
            ]);
        }

        return CustomField::create([
            'tenant_id' => $tenantId,
            'entity_type' => self::ENTITY_LEAD,
            'slug' => $slug,
            'label' => trim($data['label']),
            'type' => $data['type'],
            'options' => $data['type'] === 'select' ? $this->normalizeOptions($data['options'] ?? []) : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'sort_order' => (int) ($existing->max('sort_order') ?? -1) + 1,
        ]);
    }

    /**
     * Label, options and the required flag are editable; `slug` and `type` are not.
     *
     * The slug is frozen because saved smart-list filters reference it as
     * `custom_field:<slug>`, and the type is frozen because every stored value
     * lives in the column that type chose — changing it would orphan the data in
     * a column nothing reads.
     *
     * @param  array{label?: string, options?: list<array{value: string, label: string}>|null, is_required?: bool}  $data
     */
    public function update(CustomField $field, array $data): CustomField
    {
        $attributes = [];

        if (array_key_exists('label', $data)) {
            $attributes['label'] = trim($data['label']);
        }

        if (array_key_exists('is_required', $data)) {
            $attributes['is_required'] = (bool) $data['is_required'];
        }

        if ($field->type === 'select' && array_key_exists('options', $data)) {
            $attributes['options'] = $this->normalizeOptions($data['options'] ?? []);
        }

        $field->update($attributes);

        return $field->refresh();
    }

    /**
     * Deleting the definition drops every stored value with it (the foreign key
     * cascades), which is why the settings page shows the count first.
     */
    public function delete(CustomField $field): void
    {
        $field->delete();
    }

    /**
     * Persist a new display order. Ids that don't belong to the tenant are ignored
     * rather than rejected, so a stale page can't reorder someone else's fields.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(string $tenantId, array $orderedIds): void
    {
        $fields = $this->definitionsForTenant($tenantId)->keyBy('id');
        $position = 0;

        foreach ($orderedIds as $id) {
            $field = $fields->get((int) $id);

            if ($field === null) {
                continue;
            }

            $field->update(['sort_order' => $position++]);
        }
    }

    /**
     * Definitions annotated with this lead's current value, ready for the panel.
     *
     * @return list<array{id: int, slug: string, label: string, type: string, options: list<array{value: string, label: string}>, is_required: bool, editable: bool, value: mixed}>
     */
    public function forLead(Lead $lead): array
    {
        $definitions = $this->definitionsForTenant((string) $lead->tenant_id);

        if ($definitions->isEmpty()) {
            return [];
        }

        $values = CustomFieldValue::query()
            ->whereIn('custom_field_id', $definitions->pluck('id'))
            ->where('entity_type', self::ENTITY_LEAD)
            ->where('entity_id', $lead->id)
            ->get()
            ->keyBy('custom_field_id');

        return $definitions
            ->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'slug' => $field->slug,
                'label' => $field->label,
                'type' => $field->type,
                'options' => $this->normalizeOptions($field->options),
                'is_required' => (bool) $field->is_required,
                'editable' => $this->isEditable($field->type),
                'value' => $this->presentValue($field, $values->get($field->id)),
            ])
            ->all();
    }

    /**
     * Write the values submitted from the panel, keyed by field slug.
     *
     * A blank submission clears the value instead of failing, so the operator can
     * undo a wrong entry the same way they made it. `is_required` is a display hint
     * only: the panel collects information as the conversation reveals it, so
     * refusing to save the fields that *are* known would be the wrong trade.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    public function writeForLead(Lead $lead, array $values): void
    {
        $definitions = $this->definitionsForTenant((string) $lead->tenant_id)->keyBy('slug');

        foreach ($values as $slug => $raw) {
            $field = $definitions->get((string) $slug);

            if ($field === null || ! $this->isEditable($field->type)) {
                throw ValidationException::withMessages([
                    "values.$slug" => 'Este campo não existe mais. Recarregue a página.',
                ]);
            }

            CustomFieldValue::updateOrCreate(
                [
                    'custom_field_id' => $field->id,
                    'entity_type' => self::ENTITY_LEAD,
                    'entity_id' => $lead->id,
                ],
                [$field->valueColumn() => $this->castForStorage($field, $raw)],
            );
        }
    }

    private function isEditable(string $type): bool
    {
        return ! in_array($type, self::READ_ONLY_TYPES, true);
    }

    /**
     * FilterSchema accepts `custom_field:<slug>` only as `[a-z0-9_]+`, so the slug
     * is built with underscores and stripped of anything else.
     *
     * @throws ValidationException
     */
    private function slugFrom(string $label): string
    {
        $slug = trim((string) preg_replace('/_+/', '_', Str::slug($label, '_')), '_');

        if ($slug === '') {
            throw ValidationException::withMessages([
                'label' => 'Use ao menos uma letra ou número no nome do campo.',
            ]);
        }

        return Str::limit($slug, 60, '');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function normalizeOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $option) {
            $label = is_array($option) ? trim((string) ($option['label'] ?? '')) : trim((string) $option);

            if ($label === '') {
                continue;
            }

            $value = is_array($option) && ! empty($option['value'])
                ? (string) $option['value']
                : $this->optionValueFrom($label);

            $normalized[$value] = ['value' => $value, 'label' => $label];
        }

        return array_values($normalized);
    }

    private function optionValueFrom(string $label): string
    {
        $value = trim((string) preg_replace('/_+/', '_', Str::slug($label, '_')), '_');

        return $value === '' ? (string) Str::uuid() : $value;
    }

    /**
     * The stored value in the shape the frontend input for that type expects.
     */
    private function presentValue(CustomField $field, ?CustomFieldValue $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            'number' => $value->value_number === null ? null : (float) $value->value_number,
            'date' => $value->value_date?->toDateString(),
            'boolean' => $value->value_boolean,
            'json' => $value->value_json,
            default => $value->value_text,
        };
    }

    /**
     * @throws ValidationException
     */
    private function castForStorage(CustomField $field, mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($field->type) {
            'number' => $this->castNumber($field, $raw),
            'date' => $this->castDate($field, $raw),
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'select' => $this->castSelect($field, $raw),
            default => $this->castText($field, $raw),
        };
    }

    /**
     * @throws ValidationException
     */
    private function castNumber(CustomField $field, mixed $raw): float
    {
        if (! is_numeric($raw)) {
            throw ValidationException::withMessages([
                "values.{$field->slug}" => 'Informe um número.',
            ]);
        }

        return (float) $raw;
    }

    /**
     * @throws ValidationException
     */
    private function castDate(CustomField $field, mixed $raw): string
    {
        try {
            return Carbon::parse((string) $raw)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                "values.{$field->slug}" => 'Informe uma data válida.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function castSelect(CustomField $field, mixed $raw): string
    {
        $allowed = array_column($this->normalizeOptions($field->options), 'value');

        if (! in_array((string) $raw, $allowed, true)) {
            throw ValidationException::withMessages([
                "values.{$field->slug}" => 'Escolha uma das opções disponíveis.',
            ]);
        }

        return (string) $raw;
    }

    /**
     * @throws ValidationException
     */
    private function castText(CustomField $field, mixed $raw): string
    {
        $text = trim((string) $raw);

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw ValidationException::withMessages([
                "values.{$field->slug}" => 'Máximo de '.self::MAX_TEXT_LENGTH.' caracteres.',
            ]);
        }

        return $text;
    }
}
