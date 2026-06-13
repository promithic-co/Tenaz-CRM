<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim($request->string('q'));

        if (strlen($q) < 2) {
            return response()->json(['leads' => [], 'agents' => []]);
        }

        $tenantId = auth()->user()->tenantId;

        $leads = Lead::query()
            ->forTenant($tenantId)
            ->production()
            ->where(function ($query) use ($q) {
                $query->where('nome', 'like', "%{$q}%")
                    ->orWhere('whatsapp', 'like', "%{$q}%")
                    ->orWhere('cpf', 'like', "%{$q}%");
            })
            ->select(['id', 'nome', 'whatsapp', 'status'])
            ->limit(5)
            ->get();

        $agents = Agent::query()
            ->where('name', 'like', "%{$q}%")
            ->select(['id', 'name', 'slug'])
            ->limit(5)
            ->get();

        return response()->json(['leads' => $leads, 'agents' => $agents]);
    }
}
