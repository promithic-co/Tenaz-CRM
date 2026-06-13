<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentOperationalRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegrasOperacionaisController extends Controller
{
    public function show(Agent $agent): Response
    {
        $this->authorize('manage', $agent);

        $rules = AgentOperationalRule::forUser(Auth::id());
        $bancosPorSigla = collect(AgentOperationalRule::$BANCOS_PADRAO)->keyBy('sigla');

        $instituicoesConfig = collect($rules->instituicoes_config ?? [])
            ->map(function (array $item) use ($bancosPorSigla): array {
                $canonical = $bancosPorSigla->get($item['sigla'] ?? '', []);

                return [
                    'codigo' => $item['codigo'] ?? $canonical['codigo'] ?? null,
                    'sigla' => $item['sigla'],
                    'nome' => $item['nome'] ?? $canonical['nome'] ?? $item['sigla'],
                    'ativo' => $item['ativo'] ?? false,
                    'produtos' => $item['produtos'] ?? [],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('regras-operacionais/Index', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
            ],
            'rules' => [
                'instituicoes_config' => $instituicoesConfig,
                'regras_globais' => $rules->regras_globais,
                'regras_especies' => $rules->regras_especies,
            ],
            'flash' => session('success'),
        ]);
    }

    public function update(Agent $agent, Request $request): RedirectResponse
    {
        $this->authorize('manage', $agent);

        $validated = $request->validate([
            'instituicoes_config' => 'required|array',
            'instituicoes_config.*.sigla' => 'required|string',
            'instituicoes_config.*.ativo' => 'required|boolean',
            'instituicoes_config.*.produtos.novo' => 'required|boolean',
            'instituicoes_config.*.produtos.refin' => 'required|boolean',
            'instituicoes_config.*.produtos.port' => 'required|boolean',
            'instituicoes_config.*.produtos.rmc' => 'required|boolean',
            'instituicoes_config.*.produtos.rcc' => 'required|boolean',

            'regras_globais.idade_minima' => 'required|integer|min:18|max:100',
            'regras_globais.idade_maxima' => 'required|integer|min:18|max:100',
            'regras_globais.valor_minimo_liberado_novo' => 'required|numeric|min:0',
            'regras_globais.valor_minimo_liberado_refin' => 'required|numeric|min:0',
            'regras_globais.valor_minimo_parcela_portabilidade' => 'required|numeric|min:0',
            'regras_globais.percentual_minimo_pago_portabilidade' => 'required|numeric|min:0|max:1',

            'regras_especies.aceita_invalidez_abaixo_60' => 'required|boolean',
            'regras_especies.aceita_loas_emprestimo' => 'required|boolean',
            'regras_especies.aceita_loas_cartao' => 'required|boolean',
        ]);

        $bancosPorSigla = collect(AgentOperationalRule::$BANCOS_PADRAO)->keyBy('sigla');
        $validated['instituicoes_config'] = collect($validated['instituicoes_config'])
            ->map(function (array $item) use ($bancosPorSigla): array {
                $canonical = $bancosPorSigla->get($item['sigla'], []);

                return [
                    'codigo' => $canonical['codigo'] ?? null,
                    'sigla' => $item['sigla'],
                    'nome' => $canonical['nome'] ?? $item['sigla'],
                    'ativo' => $item['ativo'],
                    'produtos' => $item['produtos'],
                ];
            })
            ->values()
            ->all();

        AgentOperationalRule::forUser(Auth::id())->update($validated);

        return back()->with('success', 'Regras operacionais salvas com sucesso.');
    }
}
