<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppProvider;
use App\Http\Requests\StoreWhatsappInstanceRequest;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\WhatsApp\MetaTokenExchangeService;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use App\Support\RoleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppInstanceController extends Controller
{
    public function __construct(
        private readonly WhatsAppProviderFactory $factory,
        private readonly MetaTokenExchangeService $metaTokenService,
    ) {}

    public function index(Request $request): Response
    {
        $instances = RoleScope::applyOwnerScope(WhatsappInstance::query())
            ->with('agent:id,name')
            ->orderBy('created_at')
            ->get();

        foreach ($instances as $instance) {
            $manager = $this->factory->makeInstanceManager($instance);
            $data = $manager->status();
            $state = $data['state'] ?? 'close';

            if ($state === 'open') {
                $info = $manager->fetchInstanceInfo();
                if ($info && isset($info['phone_number']) && $instance->phone_number !== $info['phone_number']) {
                    $instance->update(['phone_number' => $info['phone_number']]);
                }
            }
        }

        $agentIds = $instances->pluck('agent_id')->filter()->unique()->values();

        $leadCounts = $agentIds->isEmpty()
            ? collect()
            : Lead::query()
                ->whereIn('agent_id', $agentIds)
                ->selectRaw('agent_id, COUNT(*) as total')
                ->groupBy('agent_id')
                ->pluck('total', 'agent_id');

        $payload = $instances->map(fn (WhatsappInstance $i) => [
            'id' => $i->id,
            'name' => $i->name,
            'display_name' => $i->display_name,
            'label' => $i->label(),
            'api_url' => $i->api_url,
            'phone_number' => $i->phone_number,
            'provider' => $i->provider->value,

            // Meta Cloud details
            'meta_waba_id' => $i->meta_waba_id,
            'meta_phone_number_id' => $i->meta_phone_number_id,
            'meta_quality_rating' => $i->meta_quality_rating,
            'meta_token_permanent' => (bool) $i->meta_token_permanent,
            'meta_token_expires_at' => $i->meta_token_expires_at?->toIso8601String(),
            'meta_coexistence' => (bool) $i->meta_coexistence,

            // Agent + AI mode
            'agent_id' => $i->agent_id,
            'agent_name' => $i->agent?->name,
            'default_ai_mode' => $i->default_ai_mode,

            // Stats
            'leads_count' => $i->agent_id ? (int) ($leadCounts[$i->agent_id] ?? 0) : 0,

            // Legacy proxy metadata
            'has_proxy' => $i->hasProxy(),
            'proxy_host' => $i->proxy_host,
            'proxy_port' => $i->proxy_port,
        ]);

        // Strict server-side allowlist for onboarding return target (D-15, D-16, D-17, T-60-05).
        // Only the literal internal path '/onboarding' is permitted; all others are discarded.
        $allowed = ['/onboarding'];
        $returnTo = in_array($request->query('return'), $allowed, true)
            ? $request->query('return')
            : null;

        return Inertia::render('whatsapp/Index', [
            'instances' => $payload,
            'flash' => session('success'),
            'return_to' => $returnTo,
        ]);
    }

    public function store(StoreWhatsappInstanceRequest $request): RedirectResponse
    {
        $provider = $request->validated('provider', 'meta_cloud');

        $attributes = [
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->tenantId,
            'name' => $request->validated('name'),
            'display_name' => $request->validated('display_name'),
            'provider' => $provider,
            'default_ai_mode' => Lead::AI_MODE_MANUAL,
        ];

        if ($provider === WhatsAppProvider::MetaCloud->value) {
            $signupToken = (string) $request->validated('meta_signup_token');
            $cached = Cache::pull("meta_signup:{$signupToken}");

            if (! $cached) {
                return back()->withErrors(['meta_signup_token' => 'Sessão de signup expirada. Refaça o processo de vinculação.']);
            }

            $attributes['meta_phone_number_id'] = (string) ($cached['phone_number_id'] ?? '');
            $attributes['meta_waba_id'] = (string) ($cached['waba_id'] ?? '');
            $attributes['meta_access_token'] = (string) ($cached['access_token'] ?? '');
            $attributes['meta_system_user_id'] = (string) ($cached['system_user_id'] ?? '') ?: null;
            $attributes['meta_token_permanent'] = (bool) ($cached['permanent'] ?? false);
            $attributes['meta_token_expires_at'] = $attributes['meta_token_permanent'] ? null : now()->addDays(60);
            $attributes['meta_coexistence'] = ($cached['mode'] ?? 'new') === 'coexistence';
            $attributes['api_url'] = 'https://graph.facebook.com';
            $attributes['api_key'] = '';

            // Register phone number on Cloud API for modes A (new) and B (migrate)
            if (! $attributes['meta_coexistence'] && $attributes['meta_phone_number_id']) {
                $pin = (string) ($request->validated('meta_pin') ?? $cached['meta_pin'] ?? '000000');
                $registered = $this->metaTokenService->registerPhoneNumber(
                    $attributes['meta_phone_number_id'],
                    $attributes['meta_access_token'],
                    $pin,
                );

                if (! $registered) {
                    return back()->withErrors([
                        'meta_pin' => 'Nao foi possivel registrar o numero na Meta. Revise o PIN e refaca a vinculacao.',
                    ]);
                }
            }

            // Subscribe WABA to this app so Meta delivers webhook events
            if ($attributes['meta_waba_id'] && $attributes['meta_access_token']) {
                $subscribed = $this->metaTokenService->subscribeWaba(
                    $attributes['meta_waba_id'],
                    $attributes['meta_access_token'],
                );

                if (! $subscribed) {
                    return back()->withErrors([
                        'meta_signup_token' => 'Nao foi possivel assinar os webhooks da WABA na Meta. Refaca a vinculacao antes de criar a instancia.',
                    ]);
                }
            }
        }

        $instance = WhatsappInstance::create($attributes);

        // Populate display phone number right away so the card reflects the WABA number on first render.
        if ($provider === WhatsAppProvider::MetaCloud->value) {
            $info = $this->factory->makeInstanceManager($instance)->fetchInstanceInfo();
            if ($info && ! empty($info['phone_number'])) {
                $instance->update(['phone_number' => $info['phone_number']]);
            }
        }

        return back()->with('success', 'Instância criada com sucesso.');
    }

    public function destroy(WhatsappInstance $instance): RedirectResponse
    {
        $this->authorize('delete', $instance);

        $instance->delete();

        return back()->with('success', 'Instância removida.');
    }

    public function status(WhatsappInstance $instance): JsonResponse
    {
        $this->authorize('view', $instance);

        $data = $this->factory->makeInstanceManager($instance)->status();
        $state = $data['state'] ?? 'close';

        return response()->json([
            'state' => $state,
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function connect(WhatsappInstance $instance): JsonResponse
    {
        $this->authorize('update', $instance);

        $data = $this->factory->makeInstanceManager($instance)->connect();

        return response()->json($data);
    }

    public function disconnect(WhatsappInstance $instance): JsonResponse
    {
        $this->authorize('update', $instance);

        $success = $this->factory->makeInstanceManager($instance)->disconnect();

        return response()->json(['success' => $success]);
    }

    /**
     * Assign (or unassign) the WhatsApp instance to a specific user in the
     * same tenant. Owner/Administrator only.
     */
    public function assign(Request $request, WhatsappInstance $instance): RedirectResponse
    {
        abort_unless($request->user()?->isOwnerOrAdmin(), 403);
        abort_if($instance->tenant_id !== $request->user()->tenantId, 404);

        $data = $request->validate([
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant_user', 'user_id')
                    ->where(fn ($q) => $q->where('tenant_id', $request->user()->tenantId)),
            ],
        ]);

        $instance->update(['user_id' => $data['user_id'] ?? null]);

        return back()->with('success', 'Instância atribuída.');
    }
}
