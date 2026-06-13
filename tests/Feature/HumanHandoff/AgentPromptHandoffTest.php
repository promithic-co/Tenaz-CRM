<?php

use App\Ai\Tools\EscalarParaHumanoTool;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('EscalarParaHumanoTool description mentions fila not external contact', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create(['tenant_id' => $user->tenantId]);

    $tool = new EscalarParaHumanoTool($lead);
    $description = (string) $tool->description();

    expect($description)->toContain('fila');
    expect($description)->not->toContain('número');
    expect($description)->not->toContain('celular externo');
    expect($description)->not->toContain('especialista entrará em contato');
});

test('EscalarParaHumanoTool schema has required fields in source', function () {
    $source = file_get_contents(app_path('Ai/Tools/EscalarParaHumanoTool.php'));

    expect($source)->toContain("'motivo'");
    expect($source)->toContain("'resumo'");
    expect($source)->toContain("'produto_escolhido'");
    expect($source)->toContain("'valor_total'");
});
