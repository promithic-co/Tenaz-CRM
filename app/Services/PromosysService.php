<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PromosysService
{
    /**
     * Direct CLT offline consultation. Returns the raw API response.
     *
     * @return array<string, mixed>
     */
    public function consultarClt(string $cpf): array
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post($this->baseUrl().'/consultaOfflineClt.php', [
                'token' => $this->getToken(),
                'cpf' => $cpf,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("ConsultaOfflineClt failed with HTTP {$response->status()}");
        }

        return $this->validatedDirectConsultaResponse($response->json(), 'ConsultaOfflineClt');
    }

    /**
     * Direct SIAPE offline consultation. Returns the raw API response.
     *
     * @return array<string, mixed>
     */
    public function consultarSiape(string $cpf): array
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post($this->baseUrl().'/consultaOfflineSiape.php', [
                'token' => $this->getToken(),
                'cpf' => $cpf,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("ConsultaOfflineSiape failed with HTTP {$response->status()}");
        }

        return $this->validatedDirectConsultaResponse($response->json(), 'ConsultaOfflineSiape');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.promosys.base_url'))
            && filled(config('services.promosys.usuario'))
            && filled(config('services.promosys.senha'));
    }

    private function getToken(): string
    {
        return Cache::remember('promosys_token', now()->endOfDay(), function (): string {
            $response = Http::asForm()
                ->timeout(10)
                ->post($this->baseUrl().'/token.php', [
                    'usuario' => config('services.promosys.usuario'),
                    'senha' => config('services.promosys.senha'),
                ]);

            $data = $response->json();

            if (($data['Code'] ?? '') !== '000' || empty($data['Token'])) {
                throw new RuntimeException('Promosys authentication failed: '.($data['Msg'] ?? 'Unknown error'));
            }

            return (string) $data['Token'];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDirectConsultaResponse(mixed $data, string $context): array
    {
        if (! is_array($data) || $data === []) {
            throw new RuntimeException("{$context} returned invalid response");
        }

        return $data;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.promosys.base_url'), '/');
    }
}
