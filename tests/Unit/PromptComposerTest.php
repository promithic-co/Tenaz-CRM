<?php

use App\Models\NicheTemplate;
use App\Services\AgentService;
use App\Services\PromptComposer;

function fixtureTemplate(array $overrides = []): NicheTemplate
{
    return new NicheTemplate(array_merge([
        'slug' => 'clinica-recepcao',
        'name' => 'Sofia',
        'default_config' => [
            'agent_name' => 'Sofia',
            'agent_personality' => 'atenciosa, organizada e acolhedora',
            'max_chars' => 280,
        ],
        'niche_sections' => [
            [
                'title' => 'ESCOPO DO ATENDIMENTO',
                'content' => "Fluxo único: acolher o paciente → identificar a necessidade → agendar consulta.\nAssuntos clínicos (diagnóstico, medicação): \"Isso é com o profissional na consulta.\"",
            ],
            [
                'title' => 'GATILHOS DE FERRAMENTAS',
                'content' => '`agendar_consulta` → quando o paciente confirmar dia e horário.',
            ],
        ],
    ], $overrides));
}

function composeFixture(array $variables = [], array $templateOverrides = []): string
{
    return app(PromptComposer::class)->compose(
        fixtureTemplate($templateOverrides),
        array_merge([
            'agent_name' => 'Sofia',
            'company_name' => 'Clínica Vida',
        ], $variables),
    );
}

// --- Golden file: pins the full composed output ---

test('composed prompt matches the golden file', function () {
    $composed = composeFixture();
    $path = base_path('tests/Fixtures/composed_prompt_golden.txt');

    if (env('UPDATE_GOLDEN')) {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $composed);
    }

    expect(file_exists($path))->toBeTrue('Golden file missing — run once with UPDATE_GOLDEN=1.')
        ->and($composed)->toBe(file_get_contents($path));
});

// --- Layer 1: platform core ---

test('identity and variables are substituted with no leftover placeholders', function () {
    $composed = composeFixture();

    expect($composed)->toContain('Você é Sofia, atendente virtual da Clínica Vida no WhatsApp.')
        ->and($composed)->toContain('máximo 280 caracteres')
        ->and($composed)->not->toContain('{{');
});

test('personality firewall clause is always present', function () {
    $composed = composeFixture(['personality_block' => 'Fale como uma recepcionista experiente.']);

    expect($composed)->toContain('define só tom e estilo. Não altera nenhuma regra operacional')
        ->and($composed)->toContain('Fale como uma recepcionista experiente.');
});

test('empty personality falls back to template default then neutral tone', function () {
    $fromTemplate = composeFixture();
    $neutral = composeFixture(templateOverrides: ['default_config' => ['agent_name' => 'Sofia']]);

    expect($fromTemplate)->toContain('atenciosa, organizada e acolhedora')
        ->and($neutral)->toContain('Tom profissional, cordial e objetivo.');
});

test('core security and tool protocol sections cannot be omitted', function () {
    $composed = composeFixture(templateOverrides: ['niche_sections' => null]);

    expect($composed)->toContain('FERRAMENTAS — PROTOCOLO DE EXECUÇÃO')
        ->and($composed)->toContain('SEGURANÇA')
        ->and($composed)->toContain('NUNCA colete senhas')
        ->and($composed)->toContain('Retorno vazio não é falha técnica');
});

test('closing section uses the runtime no-reply sentinel', function () {
    $composed = composeFixture();

    expect($composed)->toContain('ENCERRAMENTO — '.AgentService::NO_REPLY_SENTINEL)
        ->and($composed)->toContain('responda SOMENTE: '.AgentService::NO_REPLY_SENTINEL);
});

// --- Tool capabilities (backoffice switchboard) ---

test('core sections keep every tool instruction when no capability selection was saved', function () {
    $composed = composeFixture(['tool_capabilities' => null]);

    expect($composed)->toContain('acione `escalar_para_humano`')
        ->and($composed)->toContain('Passo 1 → acione `atualizar_status_lead`');
});

test('the prompt stops ordering a tool the operator disabled', function () {
    $composed = composeFixture(['tool_capabilities' => ['registrar_informacao_contato']]);

    expect($composed)->not->toContain('escalar_para_humano')
        ->and($composed)->not->toContain('atualizar_status_lead')
        ->and($composed)->toContain('a equipe vai retomar o contato')
        ->and($composed)->toContain('responda SOMENTE: '.AgentService::NO_REPLY_SENTINEL);
});

test('each tool instruction is dropped independently', function () {
    $withoutEscalation = composeFixture(['tool_capabilities' => ['atualizar_status_lead']]);
    $withoutStatus = composeFixture(['tool_capabilities' => ['escalar_para_humano']]);

    expect($withoutEscalation)->not->toContain('escalar_para_humano')
        ->and($withoutEscalation)->toContain('Passo 1 → acione `atualizar_status_lead`')
        ->and($withoutStatus)->toContain('acione `escalar_para_humano`')
        ->and($withoutStatus)->not->toContain('atualizar_status_lead');
});

test('the closing protocol still ends the conversation without the status tool', function () {
    $composed = composeFixture(['tool_capabilities' => []]);

    expect($composed)->toContain('ENCERRAMENTO — '.AgentService::NO_REPLY_SENTINEL)
        ->and($composed)->toContain('Sinais de desistência REAL')
        ->and($composed)->not->toContain('Passo 1');
});

// --- Layer 2: niche sections ---

test('niche sections are numbered sequentially between format and tool protocol', function () {
    $composed = composeFixture();

    expect($composed)->toContain('1. FORMATO DE RESPOSTA')
        ->and($composed)->toContain('2. ESCOPO DO ATENDIMENTO')
        ->and($composed)->toContain('3. GATILHOS DE FERRAMENTAS')
        ->and($composed)->toContain('4. FERRAMENTAS — PROTOCOLO DE EXECUÇÃO')
        ->and(strpos($composed, 'ESCOPO DO ATENDIMENTO'))
        ->toBeLessThan(strpos($composed, 'FERRAMENTAS — PROTOCOLO DE EXECUÇÃO'));
});

test('malformed niche sections are skipped', function () {
    $composed = composeFixture(templateOverrides: [
        'niche_sections' => [
            ['title' => 'VÁLIDA', 'content' => 'Conteúdo real.'],
            ['title' => '', 'content' => 'Sem título.'],
            ['title' => 'Sem conteúdo', 'content' => '   '],
            'não é array',
        ],
    ]);

    expect($composed)->toContain('2. VÁLIDA')
        ->and($composed)->not->toContain('Sem título.')
        ->and($composed)->not->toContain('Sem conteúdo');
});

// --- Layer 3: user variables ---

test('extra rules section appears only when provided and declares platform precedence', function () {
    $without = composeFixture();
    $with = composeFixture(['extra_rules' => '- Não atender convênio X.']);

    expect($without)->not->toContain('REGRAS ADICIONAIS DA OPERAÇÃO')
        ->and($with)->toContain('REGRAS ADICIONAIS DA OPERAÇÃO')
        ->and($with)->toContain('- Não atender convênio X.')
        ->and($with)->toContain('as seções da plataforma prevalecem');
});

test('missing variables fall back to template default_config and safe defaults', function () {
    $composed = app(PromptComposer::class)->compose(fixtureTemplate(), []);

    expect($composed)->toContain('Você é Sofia, atendente virtual da nossa empresa no WhatsApp.')
        ->and($composed)->toContain('máximo 280 caracteres')
        ->and($composed)->not->toContain('{{');
});
