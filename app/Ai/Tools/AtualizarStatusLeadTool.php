<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolResult;
use App\Models\Lead;
use App\Models\StatusMachine;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AtualizarStatusLeadTool implements Tool
{
    public function __construct(private readonly Lead $lead) {}

    public function description(): Stringable|string
    {
        return 'Atualiza status do lead: qualificado, sem_credito, desqualificado, optou_sair, convertido, escalado.';
    }

    public function handle(Request $request): Stringable|string
    {
        $status = $request['status'];

        $machine = StatusMachine::forTenant($this->lead->tenant_id ?? 'default');
        $validStatuses = $machine->getStatuses()->pluck('slug')->all();

        if (! in_array($status, $validStatuses)) {
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
            $updateData['followup_status'] = app(\App\Services\FollowUpWindowService::class)
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
        return [
            'status' => $schema->string()
                ->description('qualificado|sem_credito|desqualificado|optou_sair|convertido|escalado')
                ->required(),
        ];
    }
}
