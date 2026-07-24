<?php

namespace App\Enums;

use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\ConsultarCreditoCltTool;
use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Ai\Tools\ConsultarCreditoSiapeTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Ai\Tools\RegistrarInformacaoContatoTool;
use App\Ai\Tools\RegistrarLeadSemCreditoTool;

/**
 * The native tools an operator can switch on or off per agent.
 *
 * The backing value is the tool name the LLM sees, so a capability stored on
 * AgentConfig::$tool_capabilities reads the same in the database, in the prompt
 * and in the AiRun audit trail. Webhook tools are not listed here — those are
 * toggled row by row through ToolDefinition::$is_active.
 */
enum AgentToolCapability: string
{
    case ConsultarCreditoInss = 'consultar_credito_inss';

    case ConsultarCreditoSiape = 'consultar_credito_siape';

    case ConsultarCreditoClt = 'consultar_credito_clt';

    case RegistrarInformacaoContato = 'registrar_informacao_contato';

    case EscalarParaHumano = 'escalar_para_humano';

    case RegistrarLeadSemCredito = 'registrar_lead_sem_credito';

    case AtualizarStatusLead = 'atualizar_status_lead';

    public function label(): string
    {
        return match ($this) {
            self::ConsultarCreditoInss => 'Consultar crédito INSS',
            self::ConsultarCreditoSiape => 'Consultar crédito SIAPE',
            self::ConsultarCreditoClt => 'Consultar crédito CLT',
            self::RegistrarInformacaoContato => 'Registrar informação do contato',
            self::EscalarParaHumano => 'Escalar para humano',
            self::RegistrarLeadSemCredito => 'Registrar lead sem crédito',
            self::AtualizarStatusLead => 'Atualizar status do lead',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ConsultarCreditoInss => 'Consulta o crédito do aposentado/pensionista pelo CPF.',
            self::ConsultarCreditoSiape => 'Consulta o crédito do servidor público pelo CPF.',
            self::ConsultarCreditoClt => 'Consulta o crédito do trabalhador CLT pelo CPF.',
            self::RegistrarInformacaoContato => 'Guarda no contato as informações coletadas durante o atendimento.',
            self::EscalarParaHumano => 'Passa o atendimento para um humano quando o lead pede ou demonstra intenção.',
            self::RegistrarLeadSemCredito => 'Marca o lead como sem crédito disponível e encerra a linha de oferta.',
            self::AtualizarStatusLead => 'Move o lead no funil (qualificado, agendado, opt-out, ...).',
        };
    }

    /**
     * The tool class this capability switches on or off.
     *
     * @return class-string
     */
    public function toolClass(): string
    {
        return match ($this) {
            self::ConsultarCreditoInss => ConsultarCreditoInssTool::class,
            self::ConsultarCreditoSiape => ConsultarCreditoSiapeTool::class,
            self::ConsultarCreditoClt => ConsultarCreditoCltTool::class,
            self::RegistrarInformacaoContato => RegistrarInformacaoContatoTool::class,
            self::EscalarParaHumano => EscalarParaHumanoTool::class,
            self::RegistrarLeadSemCredito => RegistrarLeadSemCreditoTool::class,
            self::AtualizarStatusLead => AtualizarStatusLeadTool::class,
        };
    }

    /**
     * The capability governing a tool instance, or null when the tool is not
     * operator-toggleable (webhook tools, tools added without a capability).
     * Callers treat null as "always enabled" so a new tool never disappears
     * from an existing toolset by accident.
     */
    public static function fromToolClass(string $toolClass): ?self
    {
        foreach (self::cases() as $capability) {
            if ($toolClass === $capability->toolClass()) {
                return $capability;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $capability): string => $capability->value, self::cases());
    }

    /**
     * Capability catalogue for the backoffice screen.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $capability): array => [
            'value' => $capability->value,
            'label' => $capability->label(),
            'description' => $capability->description(),
        ], self::cases());
    }
}
