<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\RedirectResponse;

class ConfiguracoesController extends Controller
{
    public function index(): RedirectResponse
    {
        $agent = Agent::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (! $agent) {
            return redirect()->route('agentes.create');
        }

        return redirect()->route('agentes.followup', $agent);
    }

    public function update(): RedirectResponse
    {
        return redirect()->route('followup.index');
    }
}
