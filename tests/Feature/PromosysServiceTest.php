<?php

use App\Services\PromosysService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config([
        'services.promosys.base_url' => 'https://promosys.test/services',
        'services.promosys.usuario' => 'user-api',
        'services.promosys.senha' => 'secret-api',
    ]);
});

test('consultarClt authenticates and posts cpf with token', function () {
    Http::fake([
        'promosys.test/services/token.php' => Http::response(['Code' => '000', 'Token' => 'TOKEN-CLT'], 200),
        'promosys.test/services/consultaOfflineClt.php' => Http::response([
            'Code' => '000',
            'Consulta' => [['Trabalhador' => ['Nome' => 'MARIA TESTE']]],
        ], 200),
    ]);

    $response = app(PromosysService::class)->consultarClt('69747830191');

    expect($response['Code'])->toBe('000');

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://promosys.test/services/token.php'
        && $request['usuario'] === 'user-api'
        && $request['senha'] === 'secret-api');

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://promosys.test/services/consultaOfflineClt.php'
        && $request['token'] === 'TOKEN-CLT'
        && $request['cpf'] === '69747830191');
});

test('consultarSiape authenticates and posts cpf with token', function () {
    Http::fake([
        'promosys.test/services/token.php' => Http::response(['Code' => '000', 'Token' => 'TOKEN-SIAPE'], 200),
        'promosys.test/services/consultaOfflineSiape.php' => Http::response([
            'Code' => '000',
            'Consulta' => [['MATRICULA' => [['Codigo' => '123']]]],
        ], 200),
    ]);

    $response = app(PromosysService::class)->consultarSiape('69747830191');

    expect($response['Consulta'][0]['MATRICULA'][0]['Codigo'])->toBe('123');

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://promosys.test/services/consultaOfflineSiape.php'
        && $request['token'] === 'TOKEN-SIAPE'
        && $request['cpf'] === '69747830191');
});

test('token is cached for subsequent consultations', function () {
    $tokenCalls = 0;

    Http::fake(function (HttpRequest $request) use (&$tokenCalls) {
        if ($request->url() === 'https://promosys.test/services/token.php') {
            $tokenCalls++;

            return Http::response(['Code' => '000', 'Token' => 'TOKEN-CACHED'], 200);
        }

        return Http::response(['Code' => '000', 'Consulta' => [['Trabalhador' => ['Nome' => 'MARIA TESTE']]]], 200);
    });

    app(PromosysService::class)->consultarClt('69747830191');
    app(PromosysService::class)->consultarClt('69747830191');

    expect($tokenCalls)->toBe(1);
});

test('throws when authentication fails', function () {
    Http::fake([
        'promosys.test/services/token.php' => Http::response(['Code' => '401', 'Msg' => 'Nao autorizado'], 200),
    ]);

    app(PromosysService::class)->consultarClt('69747830191');
})->throws(RuntimeException::class, 'Promosys authentication failed');
