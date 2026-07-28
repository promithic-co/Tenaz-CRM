<?php

use App\Ai\AgentFactory;
use App\Ai\Agents\CredFlowAgent;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Ai\Tools\RegistrarInformacaoContatoTool;
use App\Ai\Tools\RegistrarLeadSemCreditoTool;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\AgentContextResolver;
use App\Services\AgentPromptComposer;
use App\Services\AgentService;
use App\Services\ConversationSessionLifecycleService;
use App\Services\IncomingConversationPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('uses the mutable config and active protected prompt when no release is published', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    $config = AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
        'agent_niche' => 'inss',
        'agent_name' => 'Agente original',
        'tool_capabilities' => null,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'status' => 'novo',
    ]);

    $composer = app(AgentPromptComposer::class);
    $template = PromptTemplate::query()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'name' => 'Prompt legado',
        'slug' => 'legacy-runtime-system',
        'type' => 'system',
        'content' => $composer->fromRaw('ORIENTACAO LEGADA V1 para {{agent_name}}'),
        'editor_mode' => 'raw',
        'version' => 1,
        'is_active' => true,
    ]);

    $firstRuntime = app(AgentFactory::class)->make($lead);
    $firstInstructions = (string) $firstRuntime->instructions();

    expect($firstRuntime)->toBeInstanceOf(CredFlowAgent::class)
        ->and($agent->published_release_id)->toBeNull()
        ->and($firstInstructions)->toContain('ORIENTACAO LEGADA V1 para Agente original')
        ->and($firstInstructions)->toContain(AgentService::NO_REPLY_SENTINEL);

    $config->update(['agent_name' => 'Agente atualizado']);

    $configTurnInstructions = (string) app(AgentFactory::class)
        ->make($lead->fresh())
        ->instructions();

    expect($configTurnInstructions)
        ->toContain('ORIENTACAO LEGADA V1 para Agente atualizado')
        ->not->toContain('ORIENTACAO LEGADA V1 para Agente original');

    $template->saveNewVersion([
        'content' => $composer->fromRaw('ORIENTACAO LEGADA V2 para {{agent_name}}'),
        'editor_mode' => 'raw',
    ]);

    $promptTurnInstructions = (string) app(AgentFactory::class)
        ->make($lead->fresh())
        ->instructions();

    expect($promptTurnInstructions)->toContain('ORIENTACAO LEGADA V2 para Agente atualizado')
        ->not->toContain('ORIENTACAO LEGADA V1');
});

it('keeps all current native tools enabled when legacy capabilities were never saved', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    AgentConfig::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
        'agent_niche' => 'inss',
        'tool_capabilities' => null,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'status' => 'novo',
    ]);

    $runtime = app(AgentFactory::class)->make($lead);
    $toolClasses = collect($runtime->tools())
        ->map(static fn (object $tool): string => $tool::class)
        ->all();

    expect($runtime)->toBeInstanceOf(CredFlowAgent::class)
        ->and($toolClasses)->toHaveCount(5)
        ->toContain(ConsultarCreditoInssTool::class)
        ->toContain(RegistrarInformacaoContatoTool::class)
        ->toContain(EscalarParaHumanoTool::class)
        ->toContain(RegistrarLeadSemCreditoTool::class)
        ->toContain(AtualizarStatusLeadTool::class);
});

it('opens a legacy conversation session without a pinned agent release', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'status' => 'novo',
    ]);

    $session = app(ConversationSessionLifecycleService::class)->ensureOpenSession($lead);

    expect($agent->published_release_id)->toBeNull()
        ->and($session->agent_release_id)->toBeNull()
        ->and($session->metadata)->toBeNull();
});

it('routes a campaign reply through the campaigns whatsapp instance agent', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
    ]);
    $contactList = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $contactList->id,
    ]);
    ContactListEntry::factory()->create([
        'contact_list_id' => $contactList->id,
        'phone' => '5511999998899',
        'opt_in_status' => 'opted_in',
    ]);
    $context = app(AgentContextResolver::class)->resolveFromInstanceName($instance->name);
    $result = app(IncomingConversationPersister::class)->persist(
        tenantId: $context['tenant_id'],
        agentId: $context['agent_id'],
        phone: '5511999998899',
        name: 'Lead da campanha',
        instanceName: $instance->name,
        aggregatedMessage: 'oi',
        mediaContext: null,
        interactionId: 'interaction-campaign-baseline',
        providerMessageId: 'wamid.CAMPAIGN.BASELINE',
    );
    $lead = $result['lead'];
    $session = ConversationSession::withoutGlobalScopes()
        ->where('lead_id', $lead->id)
        ->where('status', ConversationSession::STATUS_OPEN)
        ->first();

    expect(Schema::hasColumn('campaigns', 'agent_id'))->toBeFalse()
        ->and($campaign->whatsappInstance->agent_id)->toBe($agent->id)
        ->and($lead->agent_id)->toBe($agent->id)
        ->and($lead->campaign_id)->toBe($campaign->id)
        ->and($lead->whatsapp_instance_id)->toBe($instance->id)
        ->and($session)->not->toBeNull()
        ->and($session->open_reason)->toBe(ConversationSession::OPEN_REASON_CAMPAIGN)
        ->and($session->metadata['campaign_id'])->toBe($campaign->id);
});
