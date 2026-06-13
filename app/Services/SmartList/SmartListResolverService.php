<?php

namespace App\Services\SmartList;

use App\Exceptions\SmartList\InvalidFiltersException;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmartListResolverService
{
    public const OPT_OUT_STATUS = 'optou_sair';

    public const MATERIALIZE_LOCK_TTL = 30;

    public const CHUNK_SIZE = 500;

    /** D-07: query LIMIT 5001 → expose "5000+" when capped */
    public const PREVIEW_COUNT_CAP = 5001;

    /**
     * Build an Eloquent query for leads matching filters, tenant-scoped, opt-out excluded.
     */
    public function buildQuery(string $tenantId, array $filters): Builder
    {
        FilterSchema::validate($filters);

        $match = $filters['match'];
        $rules = $filters['rules'];

        $query = Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', self::OPT_OUT_STATUS);

        if ($rules === []) {
            return $query;
        }

        $apply = function (Builder $inner) use ($rules, $match): void {
            foreach ($rules as $rule) {
                if ($match === 'all') {
                    $this->applyRule($inner, $rule);
                } else {
                    $inner->orWhere(function (Builder $or) use ($rule): void {
                        $this->applyRule($or, $rule);
                    });
                }
            }
        };

        return $query->where($apply);
    }

    public function count(string $tenantId, array $filters): int
    {
        return $this->buildQuery($tenantId, $filters)->count();
    }

    /**
     * Capped count — returns exact count up to $cap, `capped: true` beyond.
     * D-07 lock: cap=5001 exposes "5000+" badge in preview UI (FilterPreview.vue).
     *
     * Implementation: applies LIMIT $cap on the underlying query, then counts rows
     * (cheaper than full COUNT(*) when tenant has 50k+ matching leads).
     *
     * @return array{count: int, capped: bool}
     */
    public function countCapped(string $tenantId, array $filters, int $cap = self::PREVIEW_COUNT_CAP): array
    {
        $rowCount = $this->buildQuery($tenantId, $filters)
            ->limit($cap)
            ->select('id')
            ->get()
            ->count();

        return [
            'count' => min($rowCount, $cap - 1),
            'capped' => $rowCount >= $cap,
        ];
    }

    /**
     * @return Collection<int, Lead>
     */
    public function preview(string $tenantId, array $filters, int $limit = 10): Collection
    {
        return $this->buildQuery($tenantId, $filters)
            ->with(['tags:id,name,color,slug,is_hot'])
            ->orderByDesc('last_interaction_at')
            ->limit($limit)
            ->get(['id', 'nome', 'status', 'last_interaction_at', 'tenant_id']);
    }

    /**
     * Materialize a dynamic list into contact_list_entries.
     * Acquires a cache lock to prevent concurrent dispatches from racing.
     *
     * Throws InvalidFiltersException if the resolved count exceeds
     * config('aria.smart_lists.max_resolve', 100_000) — prevents unbounded
     * INSERT operations and protects the queue from memory exhaustion.
     */
    public function materialize(ContactList $list): int
    {
        if (! $list->is_dynamic) {
            throw new \LogicException('Only dynamic lists can be materialized.');
        }

        $cap = (int) config('aria.smart_lists.max_resolve', 100_000);
        $resolvedCount = $this->count($list->tenant_id, $list->filters_json ?? []);

        if ($resolvedCount > $cap) {
            throw new InvalidFiltersException(
                sprintf('Lista muito grande (%d+ leads). Adicione mais filtros pra reduzir.', $cap)
            );
        }

        $lockKey = "smartlist-materialize:{$list->id}";

        return Cache::lock($lockKey, self::MATERIALIZE_LOCK_TTL)->block(self::MATERIALIZE_LOCK_TTL, function () use ($list): int {
            $query = $this->buildQuery($list->tenant_id, $list->filters_json ?? []);

            $list->entries()->delete();

            $count = 0;
            $query->chunkById(self::CHUNK_SIZE, function ($leads) use ($list, &$count): void {
                $rows = $leads->map(fn (Lead $lead): array => [
                    'contact_list_id' => $list->id,
                    'phone' => $lead->whatsapp,
                    'name' => $lead->nome,
                    'lead_id' => $lead->id,
                    'contact_id' => $lead->contact_id,
                    'opt_in_status' => 'opted_in',
                    'opt_in_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                ContactListEntry::insert($rows);
                $count += count($rows);
            });

            $list->update([
                'entries_count' => $count,
                'last_resolved_count' => $count,
                'last_resolved_at' => now(),
            ]);

            Log::info('Smart list materialized', [
                'list_id' => $list->id,
                'tenant_id' => $list->tenant_id,
                'count' => $count,
            ]);

            return $count;
        });
    }

    protected function applyRule(Builder $q, array $rule): void
    {
        $field = $rule['field'];
        $op = $rule['op'];
        $value = $rule['value'];

        match (true) {
            $field === 'tags' && $op === 'includes_all' => $this->applyTagsIncludesAll($q, $value),
            $field === 'tags' && $op === 'includes_any' => $q->whereHas('tags', fn (Builder $t) => $t->whereIn('slug', $value)),
            $field === 'tags' && $op === 'excludes' => $q->whereDoesntHave('tags', fn (Builder $t) => $t->whereIn('slug', $value)),
            $field === 'tag_is_hot' && $op === 'eq' => $value
                ? $q->whereHas('tags', fn (Builder $t) => $t->where('is_hot', true))
                : $q->whereDoesntHave('tags', fn (Builder $t) => $t->where('is_hot', true)),
            $field === 'tag_source' && $op === 'eq' => $q->whereHas('tags', fn (Builder $t) => $t->where('taggables.source', $value)),
            $field === 'status' && $op === 'in' => $q->whereIn('status', $value),
            $field === 'status' && $op === 'not_in' => $q->whereNotIn('status', $value),
            $field === 'agent_id' && $op === 'eq' => $q->where('agent_id', $value),
            $field === 'whatsapp_instance_id' && $op === 'eq' => $q->where('whatsapp_instance_id', $value),
            $field === 'last_interaction_at' && $op === 'older_than_days' => $q->where('last_interaction_at', '<', now()->subDays($value)),
            $field === 'last_interaction_at' && $op === 'within_last_days' => $q->where('last_interaction_at', '>=', now()->subDays($value)),
            $field === 'created_at' && $op === 'older_than_days' => $q->where('created_at', '<', now()->subDays($value)),
            $field === 'created_at' && $op === 'within_last_days' => $q->where('created_at', '>=', now()->subDays($value)),
            $field === 'has_open_ticket' && $op === 'eq' => $value
                ? $q->whereHas('tickets', fn (Builder $t) => $t->whereNull('closed_at'))
                : $q->whereDoesntHave('tickets', fn (Builder $t) => $t->whereNull('closed_at')),
            str_starts_with($field, 'custom_field:') => $this->applyCustomField($q, $field, $op, $value),
            default => throw new \LogicException("Unhandled rule: $field $op (FilterSchema should have rejected this)"),
        };
    }

    protected function applyTagsIncludesAll(Builder $q, array $slugs): void
    {
        foreach ($slugs as $slug) {
            $q->whereHas('tags', fn (Builder $t) => $t->where('slug', $slug));
        }
    }

    protected function applyCustomField(Builder $q, string $field, string $op, mixed $value): void
    {
        // MVP: defer if custom field schema not validated cheaply (51-CONTEXT decision 11).
        // For now, no-op + log warning so existing rules don't break. Phase 51.1 may revisit.
        Log::warning('custom_field filter ignored in MVP', ['field' => $field, 'op' => $op]);
    }
}
