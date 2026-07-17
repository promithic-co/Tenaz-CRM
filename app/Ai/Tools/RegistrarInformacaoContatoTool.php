<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolResult;
use App\Models\Lead;
use App\Services\ContactCollectedInformationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RegistrarInformacaoContatoTool implements Tool
{
    public function __construct(private readonly Lead $lead) {}

    public function description(): Stringable|string
    {
        return 'Registra informações relevantes sobre o atendimento ou o cliente, usando um rótulo livre e um valor objetivo. Registre apenas o que for útil para continuar o atendimento. Não registre senhas ou credenciais.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! is_array($request['informacoes'] ?? null)) {
            return ToolResult::blocked(
                'Nenhuma informação foi registrada.',
                'Envie uma lista de informações com título e valor.',
            );
        }

        $result = app(ContactCollectedInformationService::class)
            ->applyAi($this->lead, array_values($request['informacoes']));

        if ($result['saved'] === 0) {
            return ToolResult::blocked(
                'Nenhuma informação foi registrada.',
                'Envie itens com título e valor em texto e não substitua informações registradas manualmente.',
            );
        }

        return ToolResult::success(
            "{$result['saved']} informação(ões) registrada(s) no contato.",
            $result['skipped'] > 0
                ? "{$result['skipped']} item(ns) foi(foram) ignorado(s) por formato inválido ou prioridade manual."
                : null,
            $result,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'informacoes' => $schema->array()
                ->description('Informações curtas que ajudem a organizar e continuar o atendimento.')
                ->items($schema->object([
                    'label' => $schema->string()->description('Título curto, como Assunto, Interesse, Pendência ou Próximo passo.')->max(60)->required(),
                    'value' => $schema->string()->description('Informação curta e objetiva sobre o atendimento.')->max(500)->required(),
                ])->withoutAdditionalProperties())
                ->required(),
        ];
    }
}
