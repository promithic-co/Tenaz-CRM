<?php

use App\Jobs\ProcessLeadFollowUpJob;
use App\Models\AppSetting;
use App\Models\Lead;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

echo "=== INICIANDO VALIDAÇÃO E2E DA ENGINE DE FOLLOW-UP ===\n\n";

// 1. Configurar Settings para teste
AppSetting::set('followup_first_delay_minutes', '10');
AppSetting::set('followup_max_count', '4');
AppSetting::set('followup_approach', 'amigável e persuasivo');
AppSetting::set('agent_provider', 'openai');
AppSetting::set('agent_model', 'gpt-4o-mini');

echo "[1] Configurações de AppSettings injetadas.\n";

// 2. Mock Agent Response (LLM) and HTTP for WhatsApp
$mockResponse = Mockery::mock(\Laravel\Ai\Responses\AgentResponse::class);
$mockResponse->shouldReceive('__toString')->andReturn('Olá João, esta é a mensagem E2E validando o fluxo 100% nativo!');

$mockAgent = Mockery::mock(\App\Ai\Agents\CredFlowFollowUpAgent::class)->makePartial();
$mockAgent->shouldReceive('continue')->andReturnSelf();
$mockAgent->shouldReceive('prompt')->andReturn($mockResponse);

app()->bind(\App\Ai\Agents\CredFlowFollowUpAgent::class, function () use ($mockAgent) {
    return $mockAgent;
});

Http::fake([
    '*' => Http::response(['status' => 'success', 'messageid' => 'mocked-123'], 200),
]);
echo "[2] Agent (LLM) e HTTP (WhatsApp API) mockados para execução local.\n";

// 3. Criar Lead elegível (fake)
$leadPhone = '5511999999999';
$lead = Lead::where('whatsapp', $leadPhone)->first();
if (! $lead) {
    $lead = Lead::create([
        'tenant_id' => 'sandbox',
        'whatsapp' => $leadPhone,
        'nome' => 'João Silva de Teste (E2E)',
        'status' => 'qualificado',
        'is_sandbox' => true,
    ]);
}

// Setar o lead no passado para cair na regra do 1º delay (10 min)
$lead->update([
    'followup_status' => 'active',
    'followup_count' => 0,
    'last_interaction_at' => now()->subMinutes(15),
]);

echo "[3] Lead {$lead->nome} ({$leadPhone}) configurado como 'active' com 0 disparos e última interação há 15 minutos.\n";

// 4. Rodar o Command
echo "[4] Executando o comando cron: php artisan credflow:check-followups\n";
Artisan::call('credflow:check-followups');
echo Artisan::output();

$lead->refresh();
echo "[4] command executado. last_interaction_at atualizado.\n\n";

// 5. Processar o Job
echo "[5] Executando o Job de Follow-up (ProcessLeadFollowUpJob)...\n";

try {
    $job = new ProcessLeadFollowUpJob($lead);
    $whatsappService = app(\App\Services\WhatsAppService::class);
    $job->handle($whatsappService);

    $lead->refresh();
    echo "\n[RESULTADO DO JOB]\n";
    echo "- Followup Count do Lead agora é: {$lead->followup_count}\n";

    // Capturar requisições Evolution API
    $recorded = Http::recorded(function ($request) {
        return str_contains($request->url(), 'message/sendText');
    });

    if (count($recorded) > 0) {
        echo "- Mensagens geradas pelo LLM e enviadas para o WhatsApp:\n";
        foreach ($recorded as $record) {
            $request = $record[0];
            $body = json_decode($request->body(), true);
            echo '💬 TEXTO: '.($body['text'] ?? 'N/A')."\n";
        }
        echo "\n✅ VALIDAÇÃO E2E CONCLUÍDA COM SUCESSO!\n";
    } else {
        echo "❌ FALHA: O Job rodou mas NENHUMA requisição Http foi feita para WhatsApp API (Evolution).\n";
    }

} catch (\Exception $e) {
    echo '❌ FALHA NO JOB: '.$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}

echo "\n--- FIM DO TESTE E2E ---\n";
