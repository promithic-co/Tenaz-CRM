<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class BackofficeTenantController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('backoffice/tenants/Index', [
            'tenants' => Tenant::query()
                ->withCount(['users', 'agents'])
                ->orderBy('name')
                ->get(['id', 'name', 'created_at']),
        ]);
    }
}
