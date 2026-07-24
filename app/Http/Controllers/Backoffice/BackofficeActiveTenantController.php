<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\SelectActiveTenantRequest;
use App\Services\ActiveTenant;
use Illuminate\Http\RedirectResponse;

/**
 * Switches which company a super-admin is acting as. The selection lives in the
 * session so no tenant identifier ever appears in a backoffice URL.
 */
class BackofficeActiveTenantController extends Controller
{
    public function __construct(private ActiveTenant $activeTenant) {}

    public function store(SelectActiveTenantRequest $request): RedirectResponse
    {
        $selected = $this->activeTenant->selectForSuperAdmin(
            $request->user(),
            (string) $request->validated('tenant_id'),
        );

        if (! $selected) {
            return back()->with('flash_error', 'Não foi possível selecionar a empresa.');
        }

        return back()->with('flash', 'Empresa ativa alterada.');
    }

    public function destroy(): RedirectResponse
    {
        $this->activeTenant->clear();

        return back()->with('flash', 'Empresa ativa liberada.');
    }
}
