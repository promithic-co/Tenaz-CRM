<?php

namespace App\Services\WhatsApp;

use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Fills a template's dynamic fields from what the CRM already knows about the lead, so an
 * operator picks a template and sends it instead of retyping the customer's own data.
 *
 * Two resolution sources, in order of how explicit the template is about what it wants:
 *   1. The Meta example naming a field — templates authored for campaigns carry the mapped
 *      field name as the example (`{{2}}` with example "cpf"). Matched against the canonical
 *      contact columns first, then the contact's `extra_data` bag.
 *   2. Position 1 of the body, which is the greeting in practically every approved template,
 *      falling back to the contact name and then the lead's own name.
 *
 * Deliberately NOT a source: the Meta example itself. An example is sample copy ("Maria"),
 * and sending it would put a stranger's name in front of a real customer. Fields that resolve
 * to nothing come back in `unresolved` and stay the operator's job.
 */
class TemplateParameterResolver
{
    /**
     * Normalized example name => the lead/contact attribute that satisfies it.
     */
    private const CANONICAL_SOURCES = [
        'nome' => 'name',
        'name' => 'name',
        'nome_completo' => 'name',
        'primeiro_nome' => 'name',
        'cliente' => 'name',
        'cpf' => 'cpf',
        'email' => 'email',
        'e_mail' => 'email',
        'telefone' => 'phone',
        'phone' => 'phone',
        'celular' => 'phone',
        'whatsapp' => 'phone',
    ];

    public function __construct(private readonly WhatsappTemplateRenderer $renderer) {}

    /**
     * @param  array<int, mixed>  $components
     * @return array{parameters: array<string, array<string, string>>, unresolved: list<string>}
     */
    public function resolve(Lead $lead, array $components): array
    {
        $description = $this->renderer->describe($components);

        if (! $description['supported']) {
            return ['parameters' => [], 'unresolved' => []];
        }

        $parameters = [];
        $unresolved = [];

        foreach ($description['fields'] as $field) {
            $path = (string) ($field['path'] ?? '');
            $separator = strpos($path, '.');

            if ($separator === false) {
                continue;
            }

            $section = substr($path, 0, $separator);
            $key = substr($path, $separator + 1);
            $value = $this->resolveField($lead, $section, $key, $field);

            if ($value === null) {
                $unresolved[] = $path;

                continue;
            }

            $parameters[$section][$key] = $value;
        }

        return ['parameters' => $parameters, 'unresolved' => $unresolved];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function resolveField(Lead $lead, string $section, string $key, array $field): ?string
    {
        // Media headers point at a hosted asset the CRM has no source for — always operator-supplied.
        if (($field['type'] ?? 'text') !== 'text') {
            return null;
        }

        $example = is_string($field['example'] ?? null) ? $field['example'] : null;
        $named = $this->fromNamedExample($lead, $example);

        if ($named !== null) {
            return $named;
        }

        return $section === 'body' && $key === '1' ? $this->contactName($lead) : null;
    }

    private function fromNamedExample(Lead $lead, ?string $example): ?string
    {
        $wanted = $example === null ? '' : $this->normalizeKey($example);

        if ($wanted === '') {
            return null;
        }

        $canonical = self::CANONICAL_SOURCES[$wanted] ?? null;

        if ($canonical !== null) {
            $value = match ($canonical) {
                'name' => $this->contactName($lead),
                'cpf' => $this->filled($lead->contact?->cpf) ?? $this->filled($lead->cpf),
                'email' => $this->filled($lead->contact?->email),
                'phone' => $this->filled($lead->contact?->phone) ?? $this->filled($lead->whatsapp),
                default => null,
            };

            if ($value !== null) {
                return $value;
            }
        }

        return $this->fromExtraData($lead, $wanted);
    }

    private function fromExtraData(Lead $lead, string $wanted): ?string
    {
        $extraData = $lead->contact?->extra_data;

        if (! is_array($extraData)) {
            return null;
        }

        foreach ($extraData as $key => $value) {
            if (! is_scalar($value) || $this->normalizeKey((string) $key) !== $wanted) {
                continue;
            }

            $filled = $this->filled((string) $value);

            if ($filled !== null) {
                return $filled;
            }
        }

        return null;
    }

    private function contactName(Lead $lead): ?string
    {
        return $this->filled($lead->contact?->name) ?? $this->filled($lead->nome);
    }

    private function filled(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Collapse accents, case and separators so "E-mail", "e_mail" and "email" all match.
     */
    private function normalizeKey(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
