<?php

namespace App\Services;

use App\Models\ConversationSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Free-form label/value entries scoped to a single atendimento (ConversationSession).
 *
 * Mirrors ContactCollectedInformationService's storage contract (keyed map of
 * label/value/source) but lives on the session row: entries describe the current
 * service cycle, are archived with it on close, and feed the agent prompt only
 * while the session is open.
 */
class ConversationSessionInformationService
{
    private const MAX_ITEMS = 20;

    private const MAX_LABEL_LENGTH = 60;

    private const MAX_VALUE_LENGTH = 500;

    /**
     * @return list<array{key: string, label: string, value: string, source: 'manual'|'ai'}>
     */
    public function items(ConversationSession $session): array
    {
        $stored = $session->collected_information ?? [];

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

    /**
     * @param  array{operation: 'upsert'|'delete', key?: string|null, label?: string|null, value?: string|null}  $data
     */
    public function applyManual(ConversationSession $session, array $data): void
    {
        $this->mutate($session, function (array $stored) use ($data): array {
            if ($data['operation'] === 'delete') {
                $key = $this->normalizeKey((string) ($data['key'] ?? ''));
                if ($key === '') {
                    throw ValidationException::withMessages([
                        'key' => 'A chave da informação é inválida.',
                    ]);
                }

                unset($stored[$key]);

                return $stored;
            }

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
                    'label' => 'O atendimento já possui o limite de 20 informações registradas.',
                ]);
            }

            $stored[$newKey] = [
                'label' => $label,
                'value' => $value,
                'source' => 'manual',
            ];

            return $stored;
        });
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutator
     */
    private function mutate(ConversationSession $session, callable $mutator): void
    {
        $lockKey = "session_collected_information:{$session->tenant_id}:{$session->id}";

        Cache::lock($lockKey, 8)->block(5, function () use ($session, $mutator): void {
            $session->refresh();

            $current = is_array($session->collected_information) ? $session->collected_information : [];
            $next = $mutator($current);

            if ($next !== $current) {
                $session->update(['collected_information' => $next]);
            }
        });
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
