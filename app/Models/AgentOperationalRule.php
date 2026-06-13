<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class AgentOperationalRule extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'instituicoes_config',
        'regras_globais',
        'regras_especies',
    ];

    protected $casts = [
        'instituicoes_config' => 'array',
        'regras_globais' => 'array',
        'regras_especies' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $rule) {
            Cache::forget("agent_rules_user_{$rule->user_id}");
            if ($rule->tenant_id) {
                Cache::forget("agent_rules_tenant_{$rule->tenant_id}");
            }
        });
    }

    // -------------------------------------------------------------------------
    // Defaults aplicados quando não há regra salva para o usuário
    // -------------------------------------------------------------------------
    public static array $BANCOS_PADRAO = [
        ['codigo' => '318', 'sigla' => 'BMG',        'nome' => 'Banco BMG',              'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => true,  'rcc' => true]],
        ['codigo' => '336', 'sigla' => 'C6',         'nome' => 'C6 Bank',                'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => '070', 'sigla' => 'BRB',        'nome' => 'Banco BRB',              'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => true,  'rcc' => true]],
        ['codigo' => '041', 'sigla' => 'BANRISUL',   'nome' => 'Banrisul',               'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => '707', 'sigla' => 'DAYCOVAL',   'nome' => 'Banco Daycoval',         'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => '335', 'sigla' => 'DIGIO',      'nome' => 'Digio',                  'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => '149', 'sigla' => 'FACTA',      'nome' => 'Facta Financeira',       'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => true,  'rcc' => true]],
        ['codigo' => '012', 'sigla' => 'INBURSA',    'nome' => 'Inbursa',                'ativo' => false, 'produtos' => ['novo' => true,  'refin' => true,  'port' => false, 'rmc' => false, 'rcc' => false]],
        ['codigo' => '341', 'sigla' => 'ITAU',       'nome' => 'Itaú Unibanco',          'ativo' => false, 'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => '623', 'sigla' => 'PAN',        'nome' => 'Banco Pan',              'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => true,  'rcc' => true]],
        ['codigo' => '079', 'sigla' => 'PICPAY',     'nome' => 'PicPay Banco',           'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => '3292', 'sigla' => 'QUALIBANK',  'nome' => 'Qualibank',              'ativo' => false, 'produtos' => ['novo' => true,  'refin' => false, 'port' => false, 'rmc' => true,  'rcc' => false]],
        ['codigo' => '422', 'sigla' => 'SAFRA',      'nome' => 'Banco Safra',            'ativo' => true,  'produtos' => ['novo' => true,  'refin' => true,  'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => null,  'sigla' => 'TOTALCASH',  'nome' => 'TotalCash',              'ativo' => false, 'produtos' => ['novo' => false, 'refin' => true,  'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => null,  'sigla' => 'HAPPY',      'nome' => 'Happy Crédito (QI Tech)', 'ativo' => false, 'produtos' => ['novo' => true,  'refin' => false, 'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => '329', 'sigla' => 'ICRED',      'nome' => 'ICRED',                  'ativo' => false, 'produtos' => ['novo' => false, 'refin' => false, 'port' => true,  'rmc' => false, 'rcc' => false]],
        ['codigo' => null,  'sigla' => 'CBA_CAIXA',  'nome' => 'CBA Caixa',              'ativo' => false, 'produtos' => ['novo' => true,  'refin' => true,  'port' => false, 'rmc' => false, 'rcc' => false]],
        ['codigo' => null,  'sigla' => 'QUEROMAIS',  'nome' => 'Quero+ Crédito',         'ativo' => false, 'produtos' => ['novo' => true,  'refin' => false, 'port' => false, 'rmc' => true,  'rcc' => true]],
        ['codigo' => null,  'sigla' => 'AMIGOZ',     'nome' => 'AMIGOZ',                 'ativo' => false, 'produtos' => ['novo' => true,  'refin' => false, 'port' => false, 'rmc' => false, 'rcc' => false]],
    ];

    public static array $REGRAS_GLOBAIS_PADRAO = [
        'idade_minima' => 18,
        'idade_maxima' => 73,
        'valor_minimo_liberado_novo' => 500.00,
        'valor_minimo_liberado_refin' => 800.00,
        'valor_minimo_parcela_portabilidade' => 50.00,
        'percentual_minimo_pago_portabilidade' => 0.40,
    ];

    public static array $REGRAS_ESPECIES_PADRAO = [
        'aceita_invalidez_abaixo_60' => false,
        'aceita_loas_emprestimo' => false,
        'aceita_loas_cartao' => true,
    ];

    public static function forUser(int $userId): self
    {
        return Cache::remember("agent_rules_user_{$userId}", now()->addHours(24), function () use ($userId) {
            $tenantId = User::find($userId)?->tenantId ?? (string) $userId;

            $rule = static::withoutGlobalScope('tenant')->firstOrCreate(
                ['user_id' => $userId],
                [
                    'tenant_id' => $tenantId,
                    'instituicoes_config' => static::$BANCOS_PADRAO,
                    'regras_globais' => static::$REGRAS_GLOBAIS_PADRAO,
                    'regras_especies' => static::$REGRAS_ESPECIES_PADRAO,
                ]
            );

            if ($rule->tenant_id !== $tenantId) {
                $rule->updateQuietly(['tenant_id' => $tenantId]);
            }

            return $rule;
        });
    }

    /**
     * Busca por tenant_id (chamadas via webhook sem Auth).
     * Como tenant_id = (string) user_id, delega para forUser() quando o tenant é numérico.
     */
    public static function forTenant(string $tenantId): self
    {
        if (is_numeric($tenantId)) {
            return static::forUser((int) $tenantId);
        }

        return Cache::remember("agent_rules_tenant_{$tenantId}", now()->addHours(24), function () use ($tenantId) {
            return static::withoutGlobalScope('tenant')->firstOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'user_id' => null,
                    'instituicoes_config' => static::$BANCOS_PADRAO,
                    'regras_globais' => static::$REGRAS_GLOBAIS_PADRAO,
                    'regras_especies' => static::$REGRAS_ESPECIES_PADRAO,
                ]
            );
        });
    }

    // -------------------------------------------------------------------------
    // Helpers de acesso seguro com fallback aos defaults
    // -------------------------------------------------------------------------

    /** Retorna array de siglas de bancos onde o produto informado está ativo */
    public function bancosAtivosParaProduto(string $produto): array
    {
        return collect($this->instituicoes_config ?? [])
            ->filter(fn ($b) => ($b['ativo'] ?? false) && ($b['produtos'][$produto] ?? false))
            ->pluck('sigla')
            ->map(fn ($s) => strtoupper($s))
            ->values()
            ->all();
    }

    public function regra(string $key): mixed
    {
        return ($this->regras_globais ?? [])[$key]
            ?? static::$REGRAS_GLOBAIS_PADRAO[$key]
            ?? null;
    }

    public function especie(string $key): bool
    {
        return (bool) (($this->regras_especies ?? [])[$key]
            ?? static::$REGRAS_ESPECIES_PADRAO[$key]
            ?? false);
    }

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
