<?php

namespace App\Support;

class ContactListFilterChipPresenter
{
    /**
     * Build human-readable filter chip groups from filters_json.
     *
     * @param  array<string, mixed>|null  $filtersJson
     * @return array<int, array{label: string, values: array<int, string>, modifier?: string}>
     */
    public static function present(?array $filtersJson): array
    {
        if (! $filtersJson || empty($filtersJson['rules'])) {
            return [];
        }

        $labels = [
            'tags' => 'Tags',
            'tag_is_hot' => 'Tag quente',
            'tag_source' => 'Origem da tag',
            'status' => 'Status',
            'agent_id' => 'Agente',
            'whatsapp_instance_id' => 'Instância',
            'last_interaction_at' => 'Inatividade',
            'created_at' => 'Criação',
            'has_open_ticket' => 'Tem ticket aberto',
        ];

        $opModifiers = [
            'includes_all' => '(todas)',
            'includes_any' => '(qualquer)',
            'excludes' => '(nenhuma)',
            'ne' => '(diferente)',
            'older_than_days' => '> {n} dias',
            'within_last_days' => '≤ {n} dias',
        ];

        $chips = [];
        foreach ($filtersJson['rules'] as $rule) {
            $field = $rule['field'] ?? null;
            $op = $rule['op'] ?? null;
            $value = $rule['value'] ?? null;
            $label = $labels[$field] ?? ($field ?? 'Campo');

            $values = match (true) {
                is_array($value) => array_map('strval', $value),
                is_bool($value) => [$value ? 'sim' : 'não'],
                $value === null => [],
                default => [(string) $value],
            };

            $modifier = null;
            if (isset($opModifiers[$op])) {
                $scalarValue = is_scalar($value) ? (string) $value : '?';
                $modifier = str_replace('{n}', $scalarValue, $opModifiers[$op]);
                if (in_array($op, ['older_than_days', 'within_last_days'], true)) {
                    $values = [$modifier]; // value embedded in modifier
                    $modifier = null;
                }
            }

            $chip = [
                'label' => $label,
                'values' => $values,
            ];

            if ($modifier !== null) {
                $chip['modifier'] = $modifier;
            }

            $chips[] = $chip;
        }

        return $chips;
    }
}
