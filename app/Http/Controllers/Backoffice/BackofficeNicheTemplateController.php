<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\NicheTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin curation of the marketplace registry (niche_templates): toggle
 * active/visibility and reorder cards. Tenant snapshots land here as
 * visibility=tenant rows a super-admin can promote to system.
 */
class BackofficeNicheTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('backoffice/niche-templates/Index', [
            'templates' => NicheTemplate::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id', 'slug', 'name', 'category', 'visibility',
                    'origin_tenant_id', 'is_active', 'sort_order',
                ]),
        ]);
    }

    public function update(NicheTemplate $nicheTemplate): RedirectResponse
    {
        $validated = request()->validate([
            'is_active' => ['required', 'boolean'],
            'visibility' => ['required', Rule::in(['system', 'tenant'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $nicheTemplate->update($validated);

        return back()->with('success', 'Modelo atualizado.');
    }
}
