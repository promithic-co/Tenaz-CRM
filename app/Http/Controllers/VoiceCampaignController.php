<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVoiceCampaignRequest;
use App\Models\ContactList;
use App\Models\VoiceCampaign;
use App\Models\VoiceInstance;
use App\Services\VoiceCampaignService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VoiceCampaignController extends Controller
{
    public function index(): Response
    {
        $campaigns = VoiceCampaign::query()
            ->where('tenant_id', auth()->user()->tenantId)
            ->with(['voiceInstance', 'contactList'])
            ->withCount('calls')
            ->withCount(['calls as answered_calls_count' => function ($query) {
                $query->where('status', 'answered');
            }])
            ->withCount(['calls as interested_calls_count' => function ($query) {
                $query->where('status', 'interested');
            }])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('campanhas-voz/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('campanhas-voz/Create', [
            'voiceInstances' => VoiceInstance::query()
                ->where('tenant_id', auth()->user()->tenantId)
                ->where('active', true)
                ->get(['id', 'name', 'display_name']),
            'contactLists' => ContactList::query()
                ->get(['id', 'name', 'entries_count']),
            'twilioPhoneNumber' => config('services.twilio.phone_number'),
        ]);
    }

    public function store(StoreVoiceCampaignRequest $request): RedirectResponse
    {
        $campaign = VoiceCampaign::create([
            'tenant_id' => auth()->user()->tenantId,
            'status' => 'draft',
            ...$request->validated(),
        ]);

        return redirect()->route('campanhas-voz.show', $campaign)
            ->with('success', 'Campanha de voz criada com sucesso.');
    }

    public function show(VoiceCampaign $voiceCampaign): Response
    {
        $this->authorize('view', $voiceCampaign);

        $voiceCampaign->load(['voiceInstance', 'contactList']);

        $interestedCalls = $voiceCampaign->calls()
            ->where('status', 'interested')
            ->with('contactListEntry')
            ->orderByDesc('called_at')
            ->get();

        $allCalls = $voiceCampaign->calls()
            ->with('contactListEntry')
            ->orderByDesc('called_at')
            ->paginate(25);

        return Inertia::render('campanhas-voz/Show', [
            'voiceCampaign' => array_merge($voiceCampaign->toArray(), [
                'answer_rate' => $voiceCampaign->answerRate(),
                'interest_rate' => $voiceCampaign->interestRate(),
            ]),
            'interestedCalls' => $interestedCalls,
            'allCalls' => $allCalls,
        ]);
    }

    public function start(VoiceCampaign $voiceCampaign, VoiceCampaignService $service): RedirectResponse
    {
        $this->authorize('update', $voiceCampaign);

        try {
            $service->start($voiceCampaign);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('success', 'Campanha de voz iniciada.');
    }

    public function pause(VoiceCampaign $voiceCampaign, VoiceCampaignService $service): RedirectResponse
    {
        $this->authorize('update', $voiceCampaign);

        try {
            $service->pause($voiceCampaign);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('success', 'Campanha de voz pausada.');
    }

    public function resume(VoiceCampaign $voiceCampaign, VoiceCampaignService $service): RedirectResponse
    {
        $this->authorize('update', $voiceCampaign);

        try {
            $service->resume($voiceCampaign);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('success', 'Campanha de voz retomada.');
    }
}
