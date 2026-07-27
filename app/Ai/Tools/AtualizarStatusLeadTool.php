<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolResult;
use App\Models\Lead;
use App\Models\StatusMachine;
use App\Services\FollowUpWindowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AtualizarStatusLeadTool implements Tool
{
    /**
     * @param  list<string>|null  $allowedStatuses
     */
    public function __construct(
        private readonly Lead $lead,
        private readonly ?array $allowedStatuses = null,
    ) {}

    public function description(): Stringable|string
    {
        if ($this->allowedStatuses !== null) {
            return 'Atualiza o status do lead somente para: '.implode(', ', $this->allowedStatuses).'.';
        }

        return 'Atualiza status do lead: qualificado, sem_credito, desqualificado, optou_sair, convertido, escalado.';
    }

    public function handle(Request $request): Stringable|string
    {
        $status = $request['status'];

        $machine = StatusMachine::forTenant($this->lead->tenant_id ?? 'default');
        $validStatuses = $machine->getStatuses()->pluck('slug')->all();

        if ($this->allowedStatuses !== null && ! in_array($status, $this->allowedStatuses, true)) {
            return ToolResult::blocked(
                "Status '{$status}' não permitido neste contexto.",
                'Use somente: '.implode(', ', $this->allowedStatuses).'.'
            );
        }

        if (! in_array($status, $validStatuses, true)) {
            return ToolResult::blocked(
                "Status '{$status}' não existe.",
                'Use um dos valores válidos: '.implode(', ', $validStatuses).'.'
            );
        }

        if (! $this->lead->canTransitionTo($status)) {
            Log::warning('aria.status_transition_blocked', [
                'lead_id' => $this->lead->id,
                'from' => $this->lead->status,
                'to' => $status,
            ]);

            return ToolResult::blocked(
                "Transição '{$this->lead->status}' → '{$status}' não permitida.",
                'O status atual já é definitivo ou a transição não faz parte do fluxo. Não tente novamente.'
            );
        }

        $updateData = ['status' => $status];

        if ($status === 'qualificado') {
            $updateData['followup_status'] = app(FollowUpWindowService::class)
                ->canSendFreeFormMessage($this->lead) ? 'active' : 'inactive';
            $updateData['followup_count'] = 0;
            $updateData['last_interaction_at'] = now();
        } elseif (in_array($status, ['optou_sair', 'convertido', 'escalado', 'desqualificado'])) {
            $updateData['followup_status'] = 'inactive';
        }

        $this->lead->update($updateData);

        return ToolResult::success("Status atualizado para '{$status}'.");
    }

    public function schema(JsonSchema $schema): array
    {
        $statuses = $this->allowedStatuses
            ?? ['qualificado', 'sem_credito', 'desqualificado', 'optou_sair', 'convertido', 'escalado'];

        return [
            'status' => $schema->string()
                ->description(implode('|', $statuses))
                ->required(),
        ];
    }
}
