<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactCollectedInformationService
{
    private const NAMESPACE = 'collected_information';

    private const MAX_ITEMS = 20;

    private const MAX_LABEL_LENGTH = 60;

    private const MAX_VALUE_LENGTH = 500;

    public function __construct(
        private readonly ContactSyncService $contactSync,
        private readonly ContactExtraDataService $extraData,
    ) {}

    /**
     * @return list<array{key: string, label: string, value: string, source: 'manual'|'ai'}>
     */
    public function items(Contact $contact): array
    {
        $stored = $contact->extra_data[self::NAMESPACE] ?? [];

        if (! is_array($stored)) {
            return [];
        }

        $items = [];
        foreach ($stored as $key => $item) {
            if (! is_string($key) || ! is_array($item)) {
                continue;
            }

            $label = is_string($item['label'] ?? null) ? trim($item['label']) : '';
            $value = is_string($item['value'] ?? null) ? trim($item['value']) : '';
            if ($label === '' || $value === '') {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'source' => ($item['source'] ?? null) === 'ai' ? 'ai' : 'manual',
            ];
        }

        return $items;
    }

    public function resolveForLead(Lead $lead): ?Contact
    {
        $lead->loadMissing('contact');

        if ($lead->contact !== null
            && (string) $lead->contact->tenant_id === (string) $lead->tenant_id) {
            return $lead->contact;
        }

        return $this->contactSync->syncFromLead($lead);
    }

    /**
     * @param  array{operation: 'upsert'|'delete', key?: string|null, label?: string|null, value?: string|null}  $data
     */
    public function applyManual(Contact $contact, array $data): void
    {
        $this->extraData->mutate($contact, function (array $extraData) use ($data): array {
            $stored = is_array($extraData[self::NAMESPACE] ?? null)
                ? $extraData[self::NAMESPACE]
                : [];

            if ($data['operation'] === 'delete') {
                $key = $this->normalizeKey((string) ($data['key'] ?? ''));
                if ($key === '') {
                    throw ValidationException::withMessages([
                        'key' => 'A chave da informação é inválida.',
                    ]);
                }

                unset($stored[$key]);
            } else {
                $label = trim((string) ($data['label'] ?? ''));
                $value = trim((string) ($data['value'] ?? ''));
                $newKey = $this->normalizeKey($label);
                $providedKey = trim((string) ($data['key'] ?? ''));
                $isEditing = $providedKey !== '';
                $oldKey = $isEditing ? $this->normalizeKey($providedKey) : null;

                if (! $this->hasStorableShape($label, $value)) {
                    throw ValidationException::withMessages([
                        'value' => 'Informe um rótulo de até 60 caracteres e um valor de até 500 caracteres.',
                    ]);
                }

                if ($newKey === '' || ($isEditing && $oldKey === '')) {
                    throw ValidationException::withMessages([
                        'label' => 'O rótulo deve conter letras ou números.',
                    ]);
                }

                if (array_key_exists($newKey, $stored)
                    && (! $isEditing || $oldKey !== $newKey)) {
                    throw ValidationException::withMessages([
                        'label' => 'Já existe uma informação com este rótulo.',
                    ]);
                }

                if ($oldKey !== null && $oldKey !== $newKey) {
                    unset($stored[$oldKey]);
                }

                if (! array_key_exists($newKey, $stored) && count($stored) >= self::MAX_ITEMS) {
                    throw ValidationException::withMessages([
                        'label' => 'O contato já possui o limite de 20 informações colhidas.',
                    ]);
                }

                $stored[$newKey] = [
                    'label' => $label,
                    'value' => $value,
                    'source' => 'manual',
                ];
            }

            $extraData[self::NAMESPACE] = $stored;

            return $extraData;
        });
    }

    /**
     * @param  list<mixed>  $items
     * @return array{saved: int, skipped: int}
     */
    public function applyAi(Lead $lead, array $items): array
    {
        $skipped = 0;
        $sanitized = [];

        foreach ($items as $item) {
            if (! is_array($item)
                || ! is_string($item['label'] ?? null)
                || ! is_string($item['value'] ?? null)) {
                $skipped++;

                continue;
            }

            $label = trim($item['label']);
            $value = trim($item['value']);

            if (! $this->hasStorableShape($label, $value) || $this->normalizeKey($label) === '') {
                $skipped++;

                continue;
            }

            $sanitized[] = ['label' => $label, 'value' => $value];
        }

        if ($sanitized === []) {
            return ['saved' => 0, 'skipped' => $skipped];
        }

        $contact = $this->resolveForLead($lead);
        if ($contact === null) {
            return ['saved' => 0, 'skipped' => $skipped + count($sanitized)];
        }

        $result = ['saved' => 0, 'skipped' => $skipped];

        $this->extraData->mutate($contact, function (array $extraData) use ($sanitized, &$result): array {
            $stored = is_array($extraData[self::NAMESPACE] ?? null)
                ? $extraData[self::NAMESPACE]
                : [];

            foreach ($sanitized as $item) {
                $key = $this->normalizeKey($item['label']);
                $existing = $stored[$key] ?? null;

                if ((is_array($existing) && ($existing['source'] ?? null) === 'manual')
                    || (! array_key_exists($key, $stored) && count($stored) >= self::MAX_ITEMS)) {
                    $result['skipped']++;

                    continue;
                }

                $stored[$key] = [
                    'label' => $item['label'],
                    'value' => $item['value'],
                    'source' => 'ai',
                ];
                $result['saved']++;
            }

            $extraData[self::NAMESPACE] = $stored;

            return $extraData;
        });

        return $result;
    }

    private function normalizeKey(string $value): string
    {
        return Str::slug(trim($value), '-');
    }

    private function hasStorableShape(string $label, string $value): bool
    {
        return $label !== ''
            && $value !== ''
            && mb_strlen($label) <= self::MAX_LABEL_LENGTH
            && mb_strlen($value) <= self::MAX_VALUE_LENGTH;
    }
}
