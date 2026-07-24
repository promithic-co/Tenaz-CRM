<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class BackofficeTenantController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('backoffice/tenants/Index', [
            'tenants' => Tenant::query()
                ->withCount([
                    'users',
                    /** Counted unscoped so the list stays complete while acting as one company. */
                    'agents' => fn (Builder $query) => $query->withoutGlobalScope('tenant'),
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'created_at']),
        ]);
    }
}
