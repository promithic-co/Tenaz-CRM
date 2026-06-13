<?php

namespace App\Http\Controllers;

use App\Actions\CreateAgentAction;
use App\Http\Requests\Onboarding\StoreOnboardingAgentRequest;
use App\Http\Requests\Onboarding\StoreOnboardingInstanceRequest;
use App\Http\Requests\Onboarding\StoreOnboardingPersonaRequest;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\AgentTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly AgentTemplateService $templateService,
        private readonly CreateAgentAction $createAgent,
    ) {}

    /**
     * Derive and render the current onboarding step.
     *
     * Step derivation (D-25):
     *  - template:  no onboarding_agent_id pointer
     *  - instance:  pointer set, no linked instance and no onboarding_whatsapp_skipped_at
     *  - persona:   pointer set AND (linked instance OR onboarding_whatsapp_skipped_at is non-null)
     *
     * Authorization: incomplete non-super-admin owners only.
     */
    public function show(): Response
    {
        $user = auth()->user();
        abort_unless(
            $user && ! $user->is_super_admin && $user->isOwner() && $user->onboarded_at === null,
            403
        );

        // Validate pointer if present
        $draftAgent = null;
        if ($user->onboarding_agent_id !== null) {
            $draftAgent = Agent::query()
                ->where('id', $user->onboarding_agent_id)
                ->where('tenant_id', $user->tenantId)
                ->first();

            abort_if($draftAgent === null, 404);
        }

        // Derive step
        $currentStep = 'template';
        if ($draftAgent !== null) {
            $hasLinkedInstance = $draftAgent->whatsappInstance()->exists();
            $hasSkipped = $user->onboarding_whatsapp_skipped_at !== null;

            if ($hasLinkedInstance || $hasSkipped) {
                $currentStep = 'persona';
            } else {
                $currentStep = 'instance';
            }
        }

        // Free instances owned by this user+tenant (for step 2 picker)
        $instances = WhatsappInstance::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->tenantId)
            ->whereNull('agent_id')
            ->get()
            ->map(fn (WhatsappInstance $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'display_name' => $i->display_name,
                'phone_number' => $i->phone_number,
            ]);

        $templates = $this->templateService->all();
        $defaultTemplate = $templates[0]['slug'] ?? null;

        // Persona values from draft config (step 3)
        $personaValues = null;
        if ($draftAgent && $currentStep === 'persona') {
            $config = AgentConfig::where('agent_id', $draftAgent->id)->first();
            if ($config) {
                $personaValues = [
                    'agent_name'        => $config->agent_name,
                    'company_name'      => $config->company_name,
                    'agent_personality' => $config->agent_personality,
                    'agent_greeting'    => $config->agent_greeting,
                ];
            }
        }

        return Inertia::render('onboarding/Index', [
            'current_step'    => $currentStep,
            'templates'       => $templates,
            'default_template' => $defaultTemplate,
            'instances'       => $instances,
            'persona_values'  => $personaValues,
        ]);
    }

    /**
     * Idempotent draft creation from selected template slug.
     *
     * Creates a new inactive agent only when onboarding_agent_id is null.
     * Reuses an existing valid tenant-owned pointer without overwriting it.
     * Aborts 403/404 for any forged, missing, or cross-tenant non-null pointer.
     * Serialized under a user-row lockForUpdate (T-60-03).
     */
    public function storeAgent(StoreOnboardingAgentRequest $request): RedirectResponse
    {
        $templateSlug = $request->validated('template_slug');

        DB::transaction(function () use ($templateSlug, $request) {
            /** @var User $user */
            $user = User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();

            if ($user->onboarding_agent_id !== null) {
                // Verify the pointer is a valid tenant-owned draft
                $existing = Agent::query()
                    ->where('id', $user->onboarding_agent_id)
                    ->where('tenant_id', $user->tenantId)
                    ->first();

                if ($existing === null) {
                    // Forged or cross-tenant pointer — reject without overwriting
                    abort(403);
                }

                // Valid existing pointer — idempotent no-op
                return;
            }

            // Resolve template and call CreateAgentAction with server-owned values
            $template = $this->templateService->find($templateSlug);
            $name = (string) ($template['name'] ?? ($template['defaults']['agent_name'] ?? $templateSlug));
            $companyName = (string) ($template['defaults']['company_name'] ?? '');

            $agent = $this->createAgent->execute(
                userId: $user->id,
                tenantId: $user->tenantId,
                name: $name,
                templateSlug: $templateSlug,
                companyName: $companyName,
                description: null,
                whatsappInstanceId: null,
            );

            // Persist pointer and reset skip marker
            $user->onboarding_agent_id = $agent->id;
            $user->onboarding_whatsapp_skipped_at = null;
            $user->save();
        });

        return redirect()->route('onboarding.show');
    }

    /**
     * Link a free instance OR persist an explicit skip.
     *
     * Serialized under a user-row lockForUpdate (T-60-03A).
     * Rules:
     *  - No existing link + instance id supplied → lock candidate, link, activate, clear skip marker.
     *  - No existing link + no instance id → set skip marker (draft stays inactive).
     *  - Existing link + same instance id → idempotent no-op.
     *  - Existing link + different instance id → reject (no steal).
     *  - Existing link + skip request → reject (marker remains null).
     */
    public function storeInstance(StoreOnboardingInstanceRequest $request): RedirectResponse
    {
        $candidateId = $request->validated('whatsapp_instance_id');

        DB::transaction(function () use ($candidateId) {
            /** @var User $user */
            $user = User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();

            // Resolve active-tenant draft
            if ($user->onboarding_agent_id === null) {
                return; // No draft yet — nothing to do
            }

            $draft = Agent::query()
                ->where('id', $user->onboarding_agent_id)
                ->where('tenant_id', $user->tenantId)
                ->first();

            if ($draft === null) {
                abort(404);
            }

            // Inspect any existing link on the draft
            $existingInstance = WhatsappInstance::query()
                ->where('agent_id', $draft->id)
                ->lockForUpdate()
                ->first();

            if ($existingInstance !== null) {
                // Already linked
                if ($candidateId === null) {
                    // Skip-after-link → reject silently (state preserved)
                    return;
                }

                if ((int) $candidateId === (int) $existingInstance->id) {
                    // Same instance — idempotent no-op
                    return;
                }

                // Different instance after link → reject without stealing
                return;
            }

            // No existing link
            if ($candidateId !== null) {
                // Lock the free candidate with explicit tenant + user scope (T-60-02)
                $candidate = WhatsappInstance::query()
                    ->where('id', $candidateId)
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $user->tenantId)
                    ->whereNull('agent_id')
                    ->lockForUpdate()
                    ->first();

                if ($candidate) {
                    $candidate->update(['agent_id' => $draft->id]);
                    $draft->update(['is_active' => true]);
                    $user->onboarding_whatsapp_skipped_at = null;
                    $user->save();
                }
                // If candidate not available (race), leave draft inactive and don't change skip marker
            } else {
                // Explicit skip (Fazer depois — D-25)
                // Only set marker if not already set (idempotent)
                if ($user->onboarding_whatsapp_skipped_at === null) {
                    $user->onboarding_whatsapp_skipped_at = now();
                    $user->save();
                }
            }
        });

        return redirect()->route('onboarding.show');
    }

    /**
     * Persist exactly the 4 persona fields, set onboarded_at, and clear transient state.
     *
     * Atomically serialized under a user-row lockForUpdate (T-60-03A).
     * A late instance request after completion is rejected by the authorization boundary
     * in storeInstance (authorize() checks onboarded_at === null).
     */
    public function storePersona(StoreOnboardingPersonaRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $agentId = DB::transaction(function () use ($validated) {
            /** @var User $user */
            $user = User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();

            // Resolve draft
            if ($user->onboarding_agent_id === null) {
                abort(404);
            }

            $draft = Agent::query()
                ->where('id', $user->onboarding_agent_id)
                ->where('tenant_id', $user->tenantId)
                ->first();

            if ($draft === null) {
                abort(404);
            }

            // Persist exactly 4 persona fields onto AgentConfig (reuse Phase 59 update path)
            AgentConfig::updateOrCreate(
                ['agent_id' => $draft->id],
                array_merge($validated, ['tenant_id' => $draft->tenant_id])
            );

            // Clear transient state and mark onboarding complete (D-09)
            $user->onboarded_at = now();
            $user->onboarding_agent_id = null;
            $user->onboarding_whatsapp_skipped_at = null;
            $user->save();

            return $draft->id;
        });

        return redirect()->route('onboarding.complete', $agentId);
    }

    /**
     * Completion summary page.
     *
     * Authorized for any owner whose tenant owns the route-bound agent.
     * Intentionally accessible after onboarded_at is set.
     * Derives is_ready from persisted relationship (D-05).
     *
     * Route model binding applies BelongsToTenant global scope; we explicitly
     * resolve the agent without it so we can return 403 instead of 404 on
     * cross-tenant access, and so the completed owner (onboarded_at set) can
     * still reach this endpoint after the wizard routes are gated.
     */
    public function complete(int $agent): Response
    {
        $user = auth()->user();

        $agentModel = Agent::withoutGlobalScopes()->find($agent);

        abort_unless(
            $user && $user->isOwner() && $agentModel && $agentModel->tenant_id === $user->tenantId,
            $agentModel ? 403 : 404
        );

        $isReady = $agentModel->is_active && $agentModel->whatsappInstance()->exists();

        $config = AgentConfig::where('agent_id', $agentModel->id)->first();

        return Inertia::render('onboarding/Index', [
            'current_step' => 'complete',
            'agent_name'   => $config?->agent_name ?? $agentModel->name,
            'is_ready'     => $isReady,
        ]);
    }
}
