<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeOldCreditsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credflow:purge-old-credits {--days=15 : Número de dias para expirar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expurga arrays JSON de crédito do INSS de leads inativos para otimizar espaço no banco de dados.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Iniciando expurgo de créditos INSS mais antigos que {$cutoffDate->format('d/m/Y')}...");

        // Procurar leads com crédito JSON preenchido onde a última interação foi antes do corte
        $query = Lead::whereNotNull('credito_json')
            ->where('last_interaction_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info('Nenhum lead elegível para expurgo encontrado. O banco de dados está leve.');

            return;
        }

        // Limpar o credito_json e forçar reset do status se for status de negociação
        $query->chunkById(500, function ($leads) {
            foreach ($leads as $lead) {
                // Se cliente voltar, ele será tratado pelo status atual, mas sem crédito
                // Ocultar a "falsa memória" retrocedendo status temporários.
                $newStatus = in_array($lead->status, ['qualificado', 'sem_credito', 'desqualificado'])
                    ? 'novo'
                    : $lead->status;

                $lead->updateQuietly([
                    'credito_json' => null,
                    'status' => $newStatus,
                ]);
            }
        });

        $this->info("Concluído! {$count} resumos de crédito foram apagados para otimizar espaço.");
    }
}
