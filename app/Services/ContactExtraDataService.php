<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ContactExtraDataService
{
    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutator
     * @return array<string, mixed>
     */
    public function mutate(Contact $contact, callable $mutator): array
    {
        $tenantId = (string) $contact->tenant_id;
        $lockKey = "contact_extra_data:{$tenantId}:{$contact->id}";

        return Cache::lock($lockKey, 8)->block(5, function () use ($contact, $tenantId, $mutator): array {
            $lockedContact = Contact::withoutGlobalScopes()
                ->whereKey($contact->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($lockedContact === null) {
                throw ValidationException::withMessages([
                    'contact' => 'O contato não pertence ao mesmo tenant.',
                ]);
            }

            $current = (array) ($lockedContact->extra_data ?? []);
            $next = $mutator($current);

            if ($next !== $current) {
                $lockedContact->update(['extra_data' => $next]);
            }

            $contact->setAttribute('extra_data', $next);

            return $next;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function merge(Contact $contact, array $values): array
    {
        return $this->mutate(
            $contact,
            fn (array $current): array => array_merge($current, $values),
        );
    }

    /**
     * @param  array<string, mixed>  $replacement
     * @param  list<string>  $reservedKeys
     * @return array<string, mixed>
     */
    public function replacePreserving(Contact $contact, array $replacement, array $reservedKeys): array
    {
        return $this->mutate($contact, function (array $current) use ($replacement, $reservedKeys): array {
            $next = $replacement;

            foreach ($reservedKeys as $reservedKey) {
                if (array_key_exists($reservedKey, $current)) {
                    $next[$reservedKey] = $current[$reservedKey];
                }
            }

            return $next;
        });
    }
}
