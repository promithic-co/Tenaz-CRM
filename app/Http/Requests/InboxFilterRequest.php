<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and normalizes the conversas inbox filter query string.
 *
 * Parity port of ConversasController::parseInboxFilters: each filter falls back
 * to its sentinel default, the sort column is whitelisted, and the direction
 * collapses to 'desc' unless it is exactly 'asc'. The instance-name -> id
 * lookup stays in the controller (it needs the tenant-scoped query); this
 * request only surfaces the raw instance string.
 */
class InboxFilterRequest extends FormRequest
{
    /** @var list<string> */
    public const SORT_COLUMNS = ['nome', 'status', 'followup_count', 'last_interaction_at', 'operational_stage'];

    /** @var list<string> */
    public const GROUPS = [Lead::INBOX_GROUP_ALL, ...Lead::INBOX_GROUPS];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalize every filter to the exact sentinel/default the legacy
     * parseInboxFilters produced before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $sort = $this->query('sort');
        $direction = $this->query('direction');
        $instance = $this->query('instance');
        $group = $this->query('group');
        $group = in_array($group, self::GROUPS, true) ? $group : Lead::INBOX_GROUP_ALL;

        $this->merge([
            'group' => $group,
            'status' => $this->query('status', 'todos'),
            'instance' => is_string($instance) ? $instance : '',
            'search' => $this->query('search', ''),
            'ai_mode' => $this->query('ai_mode', 'todos'),
            'stage' => $this->query('stage', 'todos'),
            'assigned' => $this->query('assigned', 'todos'),
            'sort' => in_array($sort, self::SORT_COLUMNS, true) ? $sort : 'last_interaction_at',
            'direction' => $this->defaultDirectionFor($group, is_string($direction) ? $direction : null),
        ]);
    }

    /**
     * The queue tab is a work queue, not a feed: the oldest unclaimed escalation
     * has waited the longest and must be picked up first. Any other tab keeps the
     * newest-first inbox order. An explicit ?direction always wins.
     */
    private function defaultDirectionFor(string $group, ?string $direction): string
    {
        if ($direction === 'asc' || $direction === 'desc') {
            return $direction;
        }

        return $group === Lead::INBOX_GROUP_QUEUE ? 'asc' : 'desc';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'in:'.implode(',', self::GROUPS)],
            'status' => ['required', 'string'],
            'instance' => ['present', 'string'],
            'search' => ['present', 'string'],
            'ai_mode' => ['required', 'string'],
            'stage' => ['required', 'string'],
            'assigned' => ['required', 'string'],
            'sort' => ['required', 'string', 'in:'.implode(',', self::SORT_COLUMNS)],
            'direction' => ['required', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * The normalized filter payload consumed by Lead::scopeInboxFiltered and the
     * inbox props envelope.
     *
     * @return array{
     *     group: string,
     *     status: string,
     *     instance: string,
     *     search: string,
     *     ai_mode: string,
     *     stage: string,
     *     assigned: string,
     *     sort: string,
     *     direction: string
     * }
     */
    public function filters(): array
    {
        /** @var array{group: string, status: string, instance: string, search: string, ai_mode: string, stage: string, assigned: string, sort: string, direction: string} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
