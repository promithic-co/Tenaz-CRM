<?php

namespace Database\Seeders;

use App\Models\NicheTemplate;
use Illuminate\Database\Seeder;

class NicheTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCreationCards();

        NicheTemplate::updateOrCreate(['slug' => 'inss-consignado'], [
            'name' => 'Empréstimo Consignado INSS',
            'is_active' => false,
            'description' => 'Template padrão para SDR de crédito consignado INSS. Cobre Novo, Refinanciamento e Cartões.',
            'status_machine' => [
                'initial_status' => 'novo',
                'statuses' => [
                    ['slug' => 'novo', 'label' => 'Novo', 'color' => 'gray', 'is_terminal' => false],
                    ['slug' => 'qualificado', 'label' => 'Qualificado', 'color' => 'green', 'is_terminal' => false],
                    ['slug' => 'sem_credito', 'label' => 'Sem Crédito', 'color' => 'yellow', 'is_terminal' => false],
                    ['slug' => 'desqualificado', 'label' => 'Desqualificado', 'color' => 'orange', 'is_terminal' => false],
                    ['slug' => 'escalado', 'label' => 'Escalado', 'color' => 'blue', 'is_terminal' => false],
                    ['slug' => 'convertido', 'label' => 'Convertido', 'color' => 'purple', 'is_terminal' => true],
                    ['slug' => 'optou_sair', 'label' => 'Optou por Sair', 'color' => 'red', 'is_terminal' => true],
                ],
                'transitions' => [
                    ['from' => 'novo', 'to' => 'qualificado'],
                    ['from' => 'novo', 'to' => 'sem_credito'],
                    ['from' => 'novo', 'to' => 'desqualificado'],
                    ['from' => 'novo', 'to' => 'optou_sair'],
                    ['from' => 'qualificado', 'to' => 'escalado'],
                    ['from' => 'qualificado', 'to' => 'optou_sair'],
                    ['from' => 'qualificado', 'to' => 'convertido'],
                    ['from' => 'sem_credito', 'to' => 'qualificado'],
                    ['from' => 'sem_credito', 'to' => 'optou_sair'],
                    ['from' => 'desqualificado', 'to' => 'qualificado'],
                    ['from' => 'desqualificado', 'to' => 'optou_sair'],
                    ['from' => 'escalado', 'to' => 'convertido'],
                    ['from' => 'escalado', 'to' => 'optou_sair'],
                ],
            ],
            'custom_fields' => [
                ['slug' => 'credito', 'label' => 'Dados de Crédito', 'type' => 'json', 'is_required' => false, 'sort_order' => 0],
                ['slug' => 'documentos', 'label' => 'Documentos Coletados', 'type' => 'json', 'is_required' => false, 'sort_order' => 1],
            ],
            'tool_definitions' => [
                [
                    'slug' => 'consultar_credito_inss',
                    'name' => 'Consultar Crédito INSS',
                    'description' => 'Consulta crédito disponível para um CPF via webhook n8n/Promosys.',
                    'type' => 'webhook',
                    'config' => [
                        'url' => '{{CREDFLOW_WEBHOOK_CONSULTA}}',
                        'method' => 'POST',
                        'timeout' => 15,
                        'response_mapping' => ['status' => 'status', 'data' => 'data'],
                    ],
                    'schema' => null,
                ],
            ],
            'prompt_templates' => [
                [
                    'slug' => 'aria-system',
                    'name' => 'Tenaz CRM — Prompt Principal (INSS)',
                    'type' => 'system',
                    'content' => "Você é {{agent_name}} — consultora virtual de crédito consignado INSS da {{company_name}}. Tom: {{agent_personality}}.\n\n## FORMATO\nMáx {{max_chars}} chars/mensagem. Uma ideia por vez. Pt-BR simples.\nTermine sempre com uma pergunta ou pedido direto.\n\n## FLUXO\n1. {{agent_greeting}}\n2. Solicite o CPF (11 dígitos).\n3. CPF recebido → acione `consultar_credito_inss` imediatamente.\n4. Resultado QUALIFICADO → informe modalidades disponíveis e valores.\n5. Colete documentos um por vez: {{required_docs}}\n6. Documentação completa → acione `escalar_para_humano`.\n\n## CRITÉRIOS\nNovo: min R\${{min_novo}} | Refin: min R\${{min_refin}} | Idade máx: {{idade_max}} | LOAS: {{aceita_loas}}{{extra_rules}}\n\n## COMPORTAMENTO\n- NUNCA invente valores. Use SOMENTE dados do Contexto ou ferramentas.\n- NUNCA colete senhas ou dados bancários.",
                    'variables_schema' => [
                        'agent_name', 'company_name', 'agent_personality', 'max_chars',
                        'agent_greeting', 'required_docs', 'extra_rules',
                        'min_novo', 'min_refin', 'min_parcela_port', 'perc_min_port',
                        'idade_max', 'aceita_loas', 'aceita_invalidez',
                    ],
                ],
                [
                    'slug' => 'aria-followup',
                    'name' => 'Tenaz CRM — Follow-up (INSS)',
                    'type' => 'followup',
                    'content' => "Você é {{agent_name}}, consultora virtual de crédito consignado INSS.\nGere uma mensagem de follow-up para este cliente via WhatsApp.\n\nTentativa {{attempt_number}} de {{max_count}}. Tom: {{tone_by_attempt}}\nTom geral: {{approach}}.\n\nRegras:\n- Máx 150 chars.\n- Seja original — nunca repita tentativas anteriores.\n- Se houver valor de crédito no Contexto, mencione-o concretamente.\n- Responda APENAS com o texto da mensagem, sem formatação.",
                    'variables_schema' => ['agent_name', 'approach', 'attempt_number', 'max_count', 'tone_by_attempt'],
                ],
            ],
            'default_config' => [
                'max_chars' => 300,
                'followup_max_count' => 4,
                'followup_approach' => 'natural',
            ],
        ]);

        NicheTemplate::updateOrCreate(['slug' => 'imobiliario'], [
            'name' => 'Imobiliário',
            'is_active' => false,
            'description' => 'Template para SDR imobiliário. Fluxo: lead → visita agendada → proposta → contrato.',
            'status_machine' => [
                'initial_status' => 'lead',
                'statuses' => [
                    ['slug' => 'lead', 'label' => 'Lead', 'color' => 'gray', 'is_terminal' => false],
                    ['slug' => 'visita_agendada', 'label' => 'Visita Agendada', 'color' => 'blue', 'is_terminal' => false],
                    ['slug' => 'proposta', 'label' => 'Proposta Enviada', 'color' => 'yellow', 'is_terminal' => false],
                    ['slug' => 'contrato', 'label' => 'Contrato', 'color' => 'green', 'is_terminal' => false],
                    ['slug' => 'fechado', 'label' => 'Fechado', 'color' => 'purple', 'is_terminal' => true],
                    ['slug' => 'desistiu', 'label' => 'Desistiu', 'color' => 'red', 'is_terminal' => true],
                ],
                'transitions' => [
                    ['from' => 'lead', 'to' => 'visita_agendada'],
                    ['from' => 'lead', 'to' => 'desistiu'],
                    ['from' => 'visita_agendada', 'to' => 'proposta'],
                    ['from' => 'visita_agendada', 'to' => 'desistiu'],
                    ['from' => 'proposta', 'to' => 'contrato'],
                    ['from' => 'proposta', 'to' => 'desistiu'],
                    ['from' => 'contrato', 'to' => 'fechado'],
                    ['from' => 'contrato', 'to' => 'desistiu'],
                ],
            ],
            'custom_fields' => [
                ['slug' => 'imovel_interesse', 'label' => 'Imóvel de Interesse', 'type' => 'text', 'is_required' => false, 'sort_order' => 0],
                ['slug' => 'faixa_preco', 'label' => 'Faixa de Preço', 'type' => 'select', 'options' => [
                    ['value' => 'ate_300k', 'label' => 'Até R$ 300k'],
                    ['value' => '300_500k', 'label' => 'R$ 300k – 500k'],
                    ['value' => 'acima_500k', 'label' => 'Acima de R$ 500k'],
                ], 'is_required' => false, 'sort_order' => 1],
                ['slug' => 'financiamento', 'label' => 'Precisa de Financiamento?', 'type' => 'boolean', 'is_required' => false, 'sort_order' => 2],
            ],
            'tool_definitions' => [],
            'prompt_templates' => [],
            'default_config' => [
                'max_chars' => 300,
                'followup_max_count' => 3,
                'followup_approach' => 'consultivo',
            ],
        ]);
    }

    /**
     * Agent-creation gallery cards (registry rows consumed by AgentTemplateService).
     * The first two mirror config/agent_templates.php so DB-first resolution keeps
     * today's behavior byte-for-byte. The CORBAN card ships inactive until the
     * generic runtime (GenericAgent) can execute its toolset.
     */
    private function seedCreationCards(): void
    {
        $baseVariablesSchema = [
            ['key' => 'agent_name', 'label' => 'Nome do agente', 'type' => 'text', 'required' => true, 'max' => 100, 'placeholder' => 'Ex: Alicia'],
            ['key' => 'company_name', 'label' => 'Nome da empresa', 'type' => 'text', 'required' => true, 'max' => 100, 'placeholder' => 'Ex: Minha Empresa'],
            ['key' => 'description', 'label' => 'Descrição (opcional)', 'type' => 'text', 'required' => false, 'max' => 255, 'placeholder' => 'Ex: WhatsApp principal de atendimento'],
        ];

        foreach (config('agent_templates.templates', []) as $slug => $tpl) {
            NicheTemplate::updateOrCreate(['slug' => $slug], [
                'name' => $tpl['name'],
                'label' => $tpl['label'],
                'description' => $tpl['description'],
                'category' => 'credito-consignado',
                'mode' => $tpl['mode'] ?? null,
                'icon' => $tpl['icon'],
                'tagline' => $tpl['tagline'],
                'use_cases' => $tpl['use_cases'] ?? [],
                'example_first_message' => $tpl['example_first_message'],
                'default_config' => $tpl['defaults'] ?? [],
                'variables_schema' => $baseVariablesSchema,
                'visibility' => 'system',
                'is_active' => true,
                'sort_order' => $slug === config('agent_templates.default') ? 0 : 10,
            ]);
        }

        NicheTemplate::updateOrCreate(['slug' => 'corban-simulador'], [
            'name' => 'Simulador CORBAN',
            'label' => 'Consulta e simulação B2B',
            'description' => 'Atende correspondentes bancários (CORBAN): recebe CPF, consulta benefício e entrega simulação real INSS multi-banco.',
            'category' => 'credito-consignado',
            'mode' => 'receptivo',
            'icon' => 'heart-handshake',
            'tagline' => 'Operacional, direto, sem rodeios.',
            'use_cases' => ['promotoras', 'CORBAN', 'simulação multi-banco'],
            'example_first_message' => 'Oi! Faço consultas e simulações de contrato INSS. Me manda o CPF do cliente que eu já te retorno.',
            'prompt_templates' => [
                [
                    'slug' => 'corban-simulador-system',
                    'name' => 'Simulador CORBAN — Prompt Principal',
                    'type' => 'system',
                    'content' => file_get_contents(database_path('seeders/data/prompt_corban_simulador_v3_3.md')),
                    'variables_schema' => ['agent_name', 'company_name', 'personality_block'],
                ],
            ],
            'default_config' => [
                'max_chars' => 500,
                'temperature' => 0.3,
            ],
            'variables_schema' => [
                ['key' => 'agent_name', 'label' => 'Nome do agente', 'type' => 'text', 'required' => true, 'max' => 100, 'placeholder' => 'Ex: Simulador Amec'],
                ['key' => 'company_name', 'label' => 'Nome da promotora', 'type' => 'text', 'required' => true, 'max' => 100, 'placeholder' => 'Ex: Amec Promotora'],
                ['key' => 'personality_block', 'label' => 'Personalidade (opcional)', 'type' => 'textarea', 'required' => false, 'max' => 1000, 'placeholder' => 'Tom e estilo do agente. Não altera regras operacionais.'],
            ],
            'visibility' => 'system',
            'is_active' => false,
            'sort_order' => 20,
        ]);
    }
}
