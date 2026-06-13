<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolResult;
use App\Models\Lead;
use App\Services\ServiceTicketLifecycleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class RegistrarLeadSemCreditoTool implements Tool
{
    public function __construct(private readonly Lead $lead) {}

    public function description(): Stringable|string
    {
        return 'Registra lead sem crédito para contato futuro. Apenas modo receptivo.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->lead->status === 'sem_credito') {
            return ToolResult::alreadyDone('Lead já registrado como sem crédito. Não repita esta ação.');
        }

        if (! $this->lead->canTransitionTo('sem_credito')) {
            return ToolResult::blocked(
                "Lead em '{$this->lead->status}' não pode ser registrado como sem crédito agora.",
                'Não tente novamente. Ajuste a abordagem conforme o status atual.'
            );
        }

        try {
            DB::transaction(function () use ($request): void {
                app(ServiceTicketLifecycleService::class)->createOpenTicket(
                    lead: $this->lead,
                    type: 'no_credit',
                    data: [
                        'reason' => 'Sem crédito',
                        'summary' => $request['confirmacao'] ?? 'Cliente confirmou interesse em ser contatado futuramente.',
                    ],
                    pauseAi: false,
                );

                $this->lead->update([
                    'status' => 'sem_credito',
                    'followup_status' => 'inactive',
                ]);
            });
        } catch (Throwable $e) {
            Log::error('aria.tool.registrar_erro', [
                'lead_id' => $this->lead->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error(
                'Erro ao registrar lead para contato futuro.',
                'Informe ao cliente que foi anotado e que entraremos em contato. Não tente novamente neste turno.'
            );
        }

        return ToolResult::success('Lead registrado para contato futuro quando houver crédito disponível.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'confirmacao' => $schema->string()
                ->description('Texto confirmando interesse do cliente em contato futuro')
                ->required(),
        ];
    }
}
