<?php

namespace App\Services;

use App\Models\NicheTemplate;

/**
 * Builds the `content` of a per-agent PromptTemplate written in the backoffice.
 *
 * GenericAgent uses a DB PromptTemplate INSTEAD of PromptComposer, so anything
 * the operator does not type is simply gone from the prompt. To keep that from
 * silently dropping the platform's protections, every prompt composed here is
 * sandwiched between blocks the editor cannot touch:
 *
 *   head  → PromptComposer::preamble()          (identity + personality firewall)
 *   body  → the operator's sections or raw text
 *   tail  → PromptComposer::coreClosingSections() (FERRAMENTAS, SEGURANÇA, ENCERRAMENTO)
 *
 * Head and tail come from PromptComposer itself — one source of truth for the
 * protected text, no second copy to drift.
 *
 * Placeholders stay unresolved: the runtime substitutes them per lead through
 * PromptTemplate::render(). That renderer does NOT strip leftovers, so the two
 * core placeholders it never provides are normalised at compose time
 * (see normalizePlaceholders).
 */
class AgentPromptComposer
{
    public function __construct(private readonly PromptComposer $core) {}

    /**
     * @param  list<array{title: string, content: string}>  $sections
     * @param  list<string>|null  $toolCapabilities  null = no restriction saved
     */
    public function fromSections(array $sections, ?array $toolCapabilities = null): string
    {
        return $this->assemble($this->normalizeSections($sections), $toolCapabilities);
    }

    /**
     * Raw mode: the operator's text goes in whole, unnumbered, and the core tail
     * is re-attached after it.
     *
     * @param  list<string>|null  $toolCapabilities
     */
    public function fromRaw(string $body, ?array $toolCapabilities = null): string
    {
        return $this->assemble([], $toolCapabilities, trim($body));
    }

    /**
     * Seed for an editor that has nothing saved yet: the same middle the runtime
     * composes today — core format plus the template's niche sections.
     *
     * @return list<array{title: string, content: string}>
     */
    public function defaultSections(?NicheTemplate $template): array
    {
        return $this->normalizeSections([
            ...$this->core->coreFormatSections(),
            ...($template?->niche_sections ?? []),
        ]);
    }

    /** The head shown read-only in the editor. */
    public function head(): string
    {
        return $this->normalizePlaceholders($this->core->preamble());
    }

    /**
     * The tail shown read-only in the editor.
     *
     * @param  list<string>|null  $toolCapabilities
     * @return list<array{title: string, content: string}>
     */
    public function tailSections(?array $toolCapabilities = null): array
    {
        return array_map(
            fn (array $section): array => [
                'title' => $this->normalizePlaceholders($section['title']),
                'content' => $this->normalizePlaceholders($section['content']),
            ],
            $this->core->coreClosingSections($this->capabilityVariables($toolCapabilities)),
        );
    }

    /**
     * @param  list<array{title: string, content: string}>  $sections
     * @param  list<string>|null  $toolCapabilities
     */
    private function assemble(array $sections, ?array $toolCapabilities, ?string $rawBody = null): string
    {
        $body = $this->head();

        if ($rawBody !== null && $rawBody !== '') {
            $body .= "\n\n".$this->normalizePlaceholders($rawBody);
        }

        $numbered = [...$sections, ...$this->tailSections($toolCapabilities)];

        foreach (array_values($numbered) as $index => $section) {
            $body .= "\n\n".$this->core->sectionHeader($index + 1, $section['title'])
                ."\n\n".trim($section['content']);
        }

        return $body;
    }

    /**
     * Drops entries without both a title and a body, exactly like the niche
     * layer does at runtime, so an empty row never becomes a headed void.
     *
     * @param  array<int, mixed>  $sections
     * @return list<array{title: string, content: string}>
     */
    private function normalizeSections(array $sections): array
    {
        return collect($sections)
            ->filter(fn (mixed $section): bool => is_array($section)
                && trim((string) ($section['title'] ?? '')) !== ''
                && trim((string) ($section['content'] ?? '')) !== '')
            ->map(fn (array $section): array => [
                'title' => $this->normalizePlaceholders(trim((string) $section['title'])),
                'content' => $this->normalizePlaceholders(trim((string) $section['content'])),
            ])
            ->values()
            ->all();
    }

    /**
     * PromptTemplate::render() leaves unknown `{{placeholders}}` in the text it
     * hands to the LLM, and the agent variable map (BaseCustomerServiceAgent::
     * buildPromptVariables) has no `personality_block` or `no_reply_sentinel`.
     * So the personality slot falls back to the variable that does exist, and
     * the sentinel — a code constant, not tenant data — is written literally.
     */
    private function normalizePlaceholders(string $content): string
    {
        return str_replace(
            ['{{personality_block}}', '{{no_reply_sentinel}}'],
            ['{{agent_personality}}', AgentService::NO_REPLY_SENTINEL],
            $content,
        );
    }

    /**
     * @param  list<string>|null  $toolCapabilities
     * @return array<string, mixed>
     */
    private function capabilityVariables(?array $toolCapabilities): array
    {
        return $toolCapabilities === null ? [] : ['tool_capabilities' => $toolCapabilities];
    }
}
