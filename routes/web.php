<?php

use App\Http\Controllers\AgentConfigController;
use App\Http\Controllers\AgenteConfigController;
use App\Http\Controllers\AgentFollowUpController;
use App\Http\Controllers\AgentsController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ConfiguracoesController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactListController;
use App\Http\Controllers\ContactListEntryController;
use App\Http\Controllers\ConversasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\LeadAutoTagController;
use App\Http\Controllers\LeadFollowUpController;
use App\Http\Controllers\LeadManagementController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\LeadTagController;
use App\Http\Controllers\MetaEmbeddedSignupController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\RegrasOperacionaisController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceTicketController;
use App\Http\Controllers\StatusPipelineController;
use App\Http\Controllers\StressTestController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UraApiKeyController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\VoiceCampaignController;
use App\Http\Controllers\VoiceInstanceController;
use App\Http\Controllers\VoicePreviewController;
use App\Http\Controllers\WhatsAppInstanceController;
use App\Http\Controllers\WhatsappTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/__version', VersionController::class)->name('meta.version');

Route::get('/', HomeRedirectController::class)->name('home');

Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invite/{token}', [InvitationController::class, 'accept'])
    ->middleware('throttle:6,1')
    ->name('invitations.accept');

Route::middleware(['auth', 'verified', 'onboarded'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Global search
    Route::get('/search', SearchController::class)->name('search');

    // Conversas / leads
    Route::get('/conversas', [ConversasController::class, 'index'])->name('conversas.index');
    Route::post('/conversas', [LeadManagementController::class, 'store'])->name('conversas.store');
    Route::post('/conversas/bulk-action', [LeadManagementController::class, 'bulkAction'])->name('conversas.bulk-action');
    Route::post('/conversas/transfer', [ConversasController::class, 'bulkTransfer'])->name('conversas.transfer');
    Route::get('/conversas/{lead}/preview', [ConversasController::class, 'preview'])->name('conversas.preview');
    Route::get('/conversas/{lead}', [ConversasController::class, 'show'])->name('conversas.show');
    Route::delete('/conversas/{lead}', [LeadManagementController::class, 'destroy'])->name('conversas.destroy');
    Route::post('/conversas/{lead}/pause', [ConversasController::class, 'pause'])->name('conversas.pause');
    Route::post('/conversas/{lead}/resume', [ConversasController::class, 'resume'])->name('conversas.resume');
    Route::post('/conversas/{lead}/claim', [ConversasController::class, 'claim'])->name('conversas.claim');
    Route::patch('/conversas/{lead}/ai-mode', [ConversasController::class, 'updateAiMode'])->name('conversas.ai-mode');
    Route::post('/conversas/{lead}/assume', [ConversasController::class, 'assume'])->name('conversas.assume');
    Route::post('/conversas/{lead}/followup-pause', [LeadFollowUpController::class, 'pause'])->name('conversas.followup.pause');
    Route::post('/conversas/{lead}/followup-resume', [LeadFollowUpController::class, 'resume'])->name('conversas.followup.resume');
    Route::post('/conversas/{lead}/followup-disable', [LeadFollowUpController::class, 'disable'])->name('conversas.followup.disable');
    Route::post('/conversas/{lead}/followup-reactivate', [LeadFollowUpController::class, 'reactivate'])->name('conversas.followup.reactivate');
    Route::post('/conversas/{lead}/clear-history', [ConversasController::class, 'clearHistory'])->name('conversas.clearHistory');
    Route::post('/conversas/{lead}/send', [ConversasController::class, 'sendMessage'])->name('conversas.send');
    Route::post('/conversas/{lead}/prepare-campaign', [LeadManagementController::class, 'prepareCampaign'])->name('conversas.prepare-campaign');
    Route::post('/conversas/{lead}/add-to-contacts', [LeadManagementController::class, 'addToContacts'])->name('conversas.add-to-contacts');
    Route::post('/leads/{lead}/status', [LeadStatusController::class, 'update'])->name('leads.status.update');

    // Contatos (CRM canonical contact identity)
    Route::get('/contatos', [ContactController::class, 'index'])->name('contatos.index');
    Route::get('/contatos/search', [ContactController::class, 'search'])->name('contatos.search');
    Route::get('/contatos/{contact}', [ContactController::class, 'show'])->name('contatos.show');
    Route::middleware('role:owner,administrator')->group(function () {
        Route::post('/contatos', [ContactController::class, 'store'])->name('contatos.store');
        Route::patch('/contatos/{contact}', [ContactController::class, 'update'])->name('contatos.update');
        Route::delete('/contatos/{contact}', [ContactController::class, 'destroy'])->name('contatos.destroy');
        Route::post('/listas-contato/{list}/contatos', [ContactController::class, 'addToList'])->name('listas-contato.add-contacts');
    });

    // Tags (tenant-scoped polymorphic) — index/search accessible to all auth users; mutations restricted to owners/admins.
    Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
    Route::middleware('role:owner,administrator')->group(function (): void {
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::patch('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
        Route::post('/leads/{lead}/tags', LeadTagController::class)->name('leads.tags.sync');
    });

    // AI auto-tag manual re-evaluation — any authenticated user with Lead update access
    Route::post('/leads/{lead}/auto-tag', [LeadAutoTagController::class, 'store'])->name('leads.auto-tag.store');

    // Pipeline (Phase 49) — Kanban board scoped to authenticated tenant.
    Route::get('/pipeline', [PipelineController::class, 'index'])->name('pipeline.index');
    Route::get('/pipeline/columns/{slug}', [PipelineController::class, 'column'])
        ->where('slug', '[a-z0-9_-]+')
        ->name('pipeline.column');
    Route::post('/pipeline/move', [PipelineController::class, 'move'])
        ->middleware('throttle:60,1')
        ->name('pipeline.move');

    // Atendimentos (Escalonamentos e Sem Margem)
    Route::get('/atendimentos', [ServiceTicketController::class, 'index'])->name('atendimentos.index');
    Route::post('/atendimentos/{ticket}/claim', [ServiceTicketController::class, 'claim'])->name('atendimentos.claim');
    Route::post('/atendimentos/{ticket}/followup-disable', [ServiceTicketController::class, 'disableFollowUp'])->name('atendimentos.followup.disable');
    Route::post('/atendimentos/{ticket}/resolve', [ServiceTicketController::class, 'resolve'])->name('atendimentos.resolve');
    Route::post('/atendimentos/{ticket}/close', [ServiceTicketController::class, 'close'])->name('atendimentos.close');
    Route::post('/atendimentos/{ticket}/return-to-ai', [ServiceTicketController::class, 'returnToAi'])->name('atendimentos.return-to-ai');
    Route::post('/atendimentos/{ticket}/keep-manual', [ServiceTicketController::class, 'keepManual'])->name('atendimentos.keep-manual');

    // Gestão de instâncias WhatsApp — reachable by incomplete owners (wizard detour, D-15/D-17)
    Route::withoutMiddleware('onboarded')->group(function () {
        Route::get('/whatsapp', [WhatsAppInstanceController::class, 'index'])->name('whatsapp.index');
        Route::post('/whatsapp', [WhatsAppInstanceController::class, 'store'])->name('whatsapp.store');
        Route::delete('/whatsapp/{instance}', [WhatsAppInstanceController::class, 'destroy'])->name('whatsapp.destroy');
        Route::get('/whatsapp/{instance}/status', [WhatsAppInstanceController::class, 'status'])->name('whatsapp.status');
        Route::post('/whatsapp/{instance}/connect', [WhatsAppInstanceController::class, 'connect'])->name('whatsapp.connect');
        Route::post('/whatsapp/{instance}/disconnect', [WhatsAppInstanceController::class, 'disconnect'])->name('whatsapp.disconnect');
        Route::post('/whatsapp/meta/embedded-signup', [MetaEmbeddedSignupController::class, 'callback'])->name('whatsapp.meta.embedded-signup');
        Route::patch('/whatsapp/{instance}/assign', [WhatsAppInstanceController::class, 'assign'])
            ->middleware('role:owner,administrator')
            ->name('whatsapp.assign');
    });

    // Configurações do agente
    Route::get('/agente', [AgenteConfigController::class, 'index'])->name('agente.index');
    Route::post('/agente', [AgenteConfigController::class, 'update'])->name('agente.update');

    // Gestão de agentes (1 instância = 1 agente)
    Route::prefix('agentes')->name('agentes.')->group(function () {
        Route::get('/', [AgentsController::class, 'index'])->name('index');
        Route::get('/create', [AgentsController::class, 'create'])->name('create');
        Route::post('/', [AgentsController::class, 'store'])->name('store');
        Route::get('/{agent}/config', [AgentConfigController::class, 'show'])->name('config');
        Route::post('/{agent}/config', [AgentConfigController::class, 'update'])->name('config.update');
        Route::patch('/{agent}', [AgentsController::class, 'update'])->name('update');
        Route::delete('/{agent}', [AgentsController::class, 'destroy'])->name('destroy');
        Route::patch('/{agent_id}/restore', [AgentsController::class, 'restore'])->name('restore');
        Route::patch('/{agent}/toggle-active', [AgentsController::class, 'toggleActive'])->name('toggle-active');
        Route::patch('/{agent}/instance', [AgentsController::class, 'updateInstance'])->name('instance.update');
        Route::patch('/{agent}/assign', [AgentsController::class, 'assign'])
            ->middleware('role:owner,administrator')
            ->name('assign');
        Route::get('/{agent}/follow-up', [AgentFollowUpController::class, 'show'])->name('followup');
        Route::post('/{agent}/follow-up', [AgentFollowUpController::class, 'update'])->name('followup.update');
        Route::get('/{agent}/regras-operacionais', [RegrasOperacionaisController::class, 'show'])->name('regras-operacionais');
        Route::put('/{agent}/regras-operacionais', [RegrasOperacionaisController::class, 'update'])->name('regras-operacionais.update');
    });

    // Configurações de follow-up (agora como submenu do Agente)
    Route::get('/agente/follow-up', [ConfiguracoesController::class, 'index'])->name('followup.index');
    Route::post('/agente/follow-up', [ConfiguracoesController::class, 'update'])->name('followup.update');

    // Redirecionar rota antiga para a nova
    Route::redirect('/configuracoes', '/agente/follow-up')->name('configuracoes.index');

    // Pipeline de status (admin/owner only)
    Route::middleware('role:owner,administrator')->prefix('configuracoes/pipeline')->name('configuracoes.pipeline.')->group(function () {
        Route::get('/', [StatusPipelineController::class, 'index'])->name('index');
        Route::post('/statuses', [StatusPipelineController::class, 'storeStatus'])->name('statuses.store');
        Route::put('/statuses/{slug}', [StatusPipelineController::class, 'updateStatus'])->name('statuses.update');
        Route::delete('/statuses/{slug}', [StatusPipelineController::class, 'destroyStatus'])->name('statuses.destroy');
        Route::post('/transitions', [StatusPipelineController::class, 'storeTransition'])->name('transitions.store');
        Route::delete('/transitions/{from}/{to}', [StatusPipelineController::class, 'destroyTransition'])->name('transitions.destroy');
        Route::post('/reorder', [StatusPipelineController::class, 'reorder'])->name('reorder');
        Route::post('/reset', [StatusPipelineController::class, 'reset'])->name('reset');
    });

    // Laboratory (observability & retry engine dashboard)
    Route::get('/laboratory', [LaboratoryController::class, 'index'])->name('laboratory');
    Route::get('/laboratory/datasets-page', [LaboratoryController::class, 'datasets'])->name('laboratory.datasets');
    Route::get('/laboratory/stress-test', [LaboratoryController::class, 'stressTest'])->name('laboratory.stress-test');
    Route::get('/laboratory/stress-test/{run}', [LaboratoryController::class, 'stressTestResults'])->name('laboratory.stress-test.results');
    Route::get('/laboratory/ai-usage', [LaboratoryController::class, 'aiUsage'])->name('laboratory.ai-usage');
    Route::get('/laboratory/health', [LaboratoryController::class, 'health'])->name('laboratory.health');
    Route::get('/laboratory/interactions/{interactionId}', [LaboratoryController::class, 'interactionTimeline'])->name('laboratory.interactions.show');
    Route::get('/laboratory/leads/{lead}/interactions', [LaboratoryController::class, 'leadInteractionTimeline'])->name('laboratory.leads.interactions');

    Route::prefix('laboratory')->name('laboratory.')->group(function () {
        Route::get('/datasets', [StressTestController::class, 'datasets'])->name('datasets.index');
        Route::post('/datasets', [StressTestController::class, 'storeDataset'])->name('datasets.store');
        Route::get('/datasets/{dataset}', [StressTestController::class, 'showDataset'])->name('datasets.show');
        Route::delete('/datasets/{dataset}', [StressTestController::class, 'destroyDataset'])->name('datasets.destroy');
        Route::post('/datasets/{dataset}/prefetch', [StressTestController::class, 'prefetchDataset'])->name('datasets.prefetch');
        Route::get('/stress-tests', [StressTestController::class, 'runs'])->name('stress-tests.index');
        Route::post('/stress-tests', [StressTestController::class, 'storeRun'])->name('stress-tests.store');
        Route::get('/stress-tests/{run}', [StressTestController::class, 'showRun'])->name('stress-tests.show');
        Route::post('/stress-tests/{run}/cancel', [StressTestController::class, 'cancelRun'])->name('stress-tests.cancel');
    });

    // Admin-only: campaigns, templates, contact lists
    Route::middleware('role:owner,administrator')->group(function () {
        Route::resource('templates', WhatsappTemplateController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('templates/sync-meta', [WhatsappTemplateController::class, 'syncMeta'])->name('templates.sync-meta');

        Route::resource('campanhas', CampaignController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::patch('campanhas/{campanha}', [CampaignController::class, 'update'])->name('campanhas.update');
        Route::post('campanhas/{campanha}/start', [CampaignController::class, 'start'])->name('campanhas.start');
        Route::post('campanhas/{campanha}/pause', [CampaignController::class, 'pause'])->name('campanhas.pause');
        Route::post('campanhas/{campanha}/resume', [CampaignController::class, 'resume'])->name('campanhas.resume');
        Route::post('campanhas/{campanha}/quality-risk/keep-paused', [CampaignController::class, 'keepPausedForQualityRisk'])
            ->name('campanhas.quality-risk.keep-paused');
        Route::post('campanhas/{campanha}/quality-risk/continue', [CampaignController::class, 'continueWithQualityRisk'])
            ->name('campanhas.quality-risk.continue');

        Route::post('listas-contato/preview', [ContactListController::class, 'preview'])->name('listas-contato.preview');
        Route::get('listas-contato/create', [ContactListController::class, 'create'])->name('listas-contato.create');
        Route::resource('listas-contato', ContactListController::class)->only(['index', 'store', 'show', 'destroy'])->parameters(['listas-contato' => 'list']);
        Route::post('listas-contato/{list}/import-csv', [ContactListController::class, 'importCsv'])->name('listas-contato.import-csv');
        Route::resource('listas-contato.entries', ContactListEntryController::class)->only(['store', 'destroy'])->shallow()->parameters(['listas-contato' => 'list']);
        Route::patch('listas-contato/{list}/filters', [ContactListController::class, 'updateFilters'])->name('listas-contato.update-filters');
        Route::post('listas-contato/{list}/refresh', [ContactListController::class, 'refresh'])->name('listas-contato.refresh');
        Route::post('listas-contato/{list}/freeze', [ContactListController::class, 'freeze'])->name('listas-contato.freeze');
    });

    Route::middleware('role:owner,administrator')->group(function () {
        // Voice Instances (Twilio)
        Route::prefix('voz')->name('voz.')->group(function () {
            Route::get('/', [VoiceInstanceController::class, 'index'])->name('index');
            Route::post('/', [VoiceInstanceController::class, 'store'])->name('store');
            Route::put('{voiceInstance}', [VoiceInstanceController::class, 'update'])->name('update');
            Route::delete('{voiceInstance}', [VoiceInstanceController::class, 'destroy'])->name('destroy');
        });

        // Voice Campaigns
        Route::prefix('campanhas-voz')->name('campanhas-voz.')->group(function () {
            Route::get('/', [VoiceCampaignController::class, 'index'])->name('index');
            Route::get('criar', [VoiceCampaignController::class, 'create'])->name('create');
            Route::post('/', [VoiceCampaignController::class, 'store'])->name('store');
            Route::get('{voiceCampaign}', [VoiceCampaignController::class, 'show'])->name('show');
            Route::post('{voiceCampaign}/start', [VoiceCampaignController::class, 'start'])->name('start');
            Route::post('{voiceCampaign}/pause', [VoiceCampaignController::class, 'pause'])->name('pause');
            Route::post('{voiceCampaign}/resume', [VoiceCampaignController::class, 'resume'])->name('resume');
        });

    });

    // URA integration API keys management
    Route::prefix('ura')->name('ura.')->group(function () {
        Route::get('/', [UraApiKeyController::class, 'index'])->name('index');
        Route::post('/', [UraApiKeyController::class, 'store'])->name('store');
        Route::patch('{uraApiKey}', [UraApiKeyController::class, 'update'])->name('update');
        Route::delete('{uraApiKey}', [UraApiKeyController::class, 'destroy'])->name('destroy');
    });

    // Voice TTS Preview (generates mp3 via Google TTS API)
    Route::post('/voz/preview-tts', [VoicePreviewController::class, 'preview'])->name('voz.preview-tts');
    // Playground (sandbox de testes do agente)
    Route::prefix('playground')->name('playground.')->group(function () {
        Route::get('/', [PlaygroundController::class, 'index'])->name('index');
        Route::post('/', [PlaygroundController::class, 'store'])->name('store');
        Route::delete('/{lead}', [PlaygroundController::class, 'destroy'])->name('destroy');
        Route::post('/{lead}/reset', [PlaygroundController::class, 'reset'])->name('reset');
        Route::post('/{lead}/prompt', [PlaygroundController::class, 'updatePrompt'])->name('updatePrompt');
        Route::get('/{lead}/messages', [PlaygroundController::class, 'messages'])->name('messages');

        // LLM-invoking endpoints — throttled as abuse control (F8). Applies to all
        // users equally; not a feature gate.
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/generate-scenario', [PlaygroundController::class, 'generateScenario'])->name('generateScenario');
            Route::post('/scan-blindspots', [PlaygroundController::class, 'scanBlindspots'])->name('scanBlindspots');
            Route::post('/{lead}/chat', [PlaygroundController::class, 'chat'])->name('chat');
            Route::post('/{lead}/tester-chat', [PlaygroundController::class, 'testerChat'])->name('testerChat');
            Route::post('/{lead}/evaluate', [PlaygroundController::class, 'evaluate'])->name('evaluate');
        });
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/backoffice.php';
require __DIR__.'/onboarding.php';
