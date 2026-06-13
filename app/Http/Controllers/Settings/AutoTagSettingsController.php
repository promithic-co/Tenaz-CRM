<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAutoTagSettingsRequest;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AutoTagSettingsController extends Controller
{
    public function edit(): Response
    {
        $tenantId = (string) auth()->user()->tenantId;

        return Inertia::render('settings/AutoTag', [
            'auto_tagging_enabled' => (bool) AppSetting::getForTenant($tenantId, 'auto_tagging_enabled', false),
            'status' => session('status'),
        ]);
    }

    public function update(UpdateAutoTagSettingsRequest $request): RedirectResponse
    {
        $tenantId = (string) $request->user()->tenantId;

        AppSetting::setForTenant(
            $tenantId,
            'auto_tagging_enabled',
            $request->boolean('auto_tagging_enabled') ? '1' : '0',
        );

        return back()->with('status', 'Configuração salva.');
    }
}
