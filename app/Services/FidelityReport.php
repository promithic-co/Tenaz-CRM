<?php

namespace App\Services;

class FidelityReport
{
    public function __construct(
        public float $score,
        public array $hallucinations,
        public array $matches,
        public bool $statusCorrect,
        public bool $passed,
    ) {}

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'hallucinations' => $this->hallucinations,
            'matches' => $this->matches,
            'status_correct' => $this->statusCorrect,
            'passed' => $this->passed,
        ];
    }

    public function toFormattedString(): string
    {
        $lines = ['## Relatório de Fidelidade de Dados'];
        $lines[] = sprintf('**Score: %.1f/100** (%s)', $this->score, $this->passed ? '✓ APROVADO' : '✗ REPROVADO');
        $lines[] = '**Status correto:** '.($this->statusCorrect ? 'Sim' : 'Não');
        $lines[] = '';

        if (! empty($this->hallucinations)) {
            $lines[] = '### Alucinações Detectadas';
            foreach ($this->hallucinations as $h) {
                $lines[] = sprintf(
                    '- **[%s]** Campo: `%s` | Esperado: `%s` | Reportado: `%s`',
                    strtoupper($h['severity']),
                    $h['field'],
                    $h['expected'],
                    $h['actual']
                );
            }
            $lines[] = '';
        }

        if (! empty($this->matches)) {
            $lines[] = '### Valores Corretos';
            foreach ($this->matches as $m) {
                $lines[] = sprintf('- `%s`: R$ %s ✓', $m['field'], number_format($m['value'], 2, ',', '.'));
            }
        }

        return implode("\n", $lines);
    }
}
