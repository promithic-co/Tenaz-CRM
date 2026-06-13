<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class DebounceService
{
    private const KEY_TTL = 10;

    /**
     * Mensagens que pulam debounce (respostas rápidas de saudação).
     */
    private const QUICK_COMMANDS = [
        'oi', 'bom dia', 'boa tarde', 'boa noite', 'ok', 'blz', '.',
        'oi bom dia', 'oi boa tarde', 'oi boa noite', 'sim', 'não',
    ];

    /**
     * Saudações curtas são processadas imediatamente, sem entrar no buffer.
     */
    public function isQuickCommand(string $message): bool
    {
        return in_array(mb_strtolower(trim($message)), self::QUICK_COMMANDS);
    }

    /**
     * Adiciona a mensagem ao buffer de debounce. Retorna true apenas para a
     * primeira mensagem da janela — quem deve agendar a drenagem (o job atrasado).
     * Mensagens subsequentes dentro da janela apenas acumulam e retornam false.
     */
    public function push(string $phone, string $message): bool
    {
        $key = "debounce:{$phone}";

        Redis::rpush($key, json_encode([
            'text' => $message,
            'timestamp' => now()->timestamp,
        ]));
        Redis::expire($key, self::KEY_TTL);

        // SETNX garante que só a primeira request da janela agenda a drenagem.
        // O lock expira sozinho caso o job nunca rode (resiliência); de qualquer
        // forma drain() o remove ao processar.
        $lockKey = "debounce:lock:{$phone}";
        $claimed = (bool) Redis::setnx($lockKey, '1');

        if ($claimed) {
            $window = (int) config('credflow.debounce_seconds', 3);
            Redis::expire($lockKey, $window + 1);
        }

        return $claimed;
    }

    /**
     * Drena o buffer acumulado e retorna o texto agregado em ordem cronológica,
     * ou null se não houver nada para processar.
     */
    public function drain(string $phone): ?string
    {
        $key = "debounce:{$phone}";

        $messages = Redis::lrange($key, 0, -1);
        Redis::del($key, "debounce:lock:{$phone}");

        if (empty($messages)) {
            return null;
        }

        return collect($messages)
            ->map(fn ($m) => json_decode($m, true))
            ->filter()
            ->sortBy('timestamp')
            ->pluck('text')
            ->join("\n");
    }
}
