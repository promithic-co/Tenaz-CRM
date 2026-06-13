<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVoiceInstanceRequest;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VoiceInstanceController extends Controller
{
    public function index(): Response
    {
        $instances = VoiceInstance::query()
            ->where('tenant_id', auth()->user()->tenantId)
            ->with('whatsappInstance')
            ->orderBy('created_at')
            ->get();

        $whatsappInstances = WhatsappInstance::query()
            ->where('tenant_id', auth()->user()->tenantId)
            ->get(['id', 'name', 'display_name']);

        return Inertia::render('voz/Index', [
            'instances' => $instances,
            'whatsappInstances' => $whatsappInstances,
            'twilioPhoneNumber' => config('services.twilio.phone_number'),
        ]);
    }

    public function store(StoreVoiceInstanceRequest $request): RedirectResponse
    {
        VoiceInstance::create([
            'tenant_id' => auth()->user()->tenantId,
            'user_id' => auth()->id(),
            ...$request->validated(),
        ]);

        return redirect()->route('voz.index')->with('success', 'Instância criada com sucesso.');
    }

    public function update(StoreVoiceInstanceRequest $request, VoiceInstance $voiceInstance): RedirectResponse
    {
        $this->authorize('update', $voiceInstance);

        $voiceInstance->update($request->validated());

        return redirect()->route('voz.index')->with('success', 'Instância atualizada com sucesso.');
    }

    public function destroy(VoiceInstance $voiceInstance): RedirectResponse
    {
        $this->authorize('delete', $voiceInstance);

        $voiceInstance->delete();

        return redirect()->route('voz.index')->with('success', 'Instância removida com sucesso.');
    }
}
