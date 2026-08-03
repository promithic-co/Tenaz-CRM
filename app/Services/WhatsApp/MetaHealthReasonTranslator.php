<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappInstance;

/**
 * Turns Meta's raw health messages into something a promotora's operator can
 * act on.
 *
 * Meta returns `additional_info` and `error_description` in English, written for
 * developers — one of the strings seen in production tells the reader to
 * "Configure SIP using {PHONE_NUMBER_ID}/settings API". Nobody outside this
 * repository can do anything with that.
 *
 * Translation happens here, at props-build time, and never on the way into the
 * database: the raw English stays on `meta_health_entities`, so rows written
 * before a phrase was mapped start rendering correctly the moment a mapping is
 * added, with no re-probe and no backfill.
 *
 * @phpstan-type TranslatedReason array{title: string, detail: string|null, action: string|null, original: string|null}
 */
class MetaHealthReasonTranslator
{
    /**
     * Messages that say nothing about the ability to send messages.
     *
     * Meta reports voice-calling problems on the same entity as messaging ones.
     * `MetaHealthService` already keeps them out of the *status*, but they were
     * still reaching the reason list and telling operators their number had a
     * problem when it only lacks calling configuration.
     *
     * @var list<string>
     */
    private const IRRELEVANT = [
        'sip',
        'business calling',
        'calling is not enabled',
        'call permissions',
    ];

    /**
     * Ordered matchers: the first whose needle appears in the raw message wins,
     * so the specific phrasings must come before the broad ones.
     *
     * @var list<array{needles: list<string>, title: string, detail: string, action: string|null}>
     */
    private const MATCHERS = [
        [
            'needles' => ['display name was rejected', 'display name is rejected', 'display name has been rejected'],
            'title' => 'A Meta recusou o nome que seus clientes veem',
            'detail' => 'O nome de exibição não passou na análise da Meta, então ele não aparece para quem recebe suas mensagens.',
            'action' => 'Escolha um nome que represente a sua empresa de verdade e envie de novo para análise no Gerenciador do WhatsApp.',
        ],
        [
            'needles' => ['display name has not been approved', 'display name is approved', 'display name is pending'],
            'title' => 'O nome que seus clientes veem ainda está em análise',
            'detail' => 'Enquanto a Meta não aprova o nome de exibição, você consegue iniciar menos conversas novas por dia.',
            'action' => 'Não precisa fazer nada: quando a Meta aprovar, o limite aumenta sozinho.',
        ],
        [
            'needles' => ['business verification', 'business is not verified', 'verify your business', 'unverified business'],
            'title' => 'Sua empresa ainda não foi verificada pela Meta',
            'detail' => 'Sem a verificação, a Meta limita bastante quantos clientes novos você alcança por dia.',
            'action' => 'Faça a verificação da empresa no Gerenciador de Negócios da Meta. Costumam pedir CNPJ e um comprovante de endereço.',
        ],
        [
            'needles' => ['payment method', 'billing', 'add a credit card'],
            'title' => 'Falta uma forma de pagamento na conta da Meta',
            'detail' => 'A Meta cobra pelas conversas iniciadas pela empresa e não encontrou um cartão válido nesta conta.',
            'action' => 'Cadastre um cartão em Configurações de pagamento, no Gerenciador de Negócios da Meta.',
        ],
        [
            'needles' => ['not registered', 'is not connected', 'register the phone number', 'must be registered'],
            'title' => 'Este número ainda não foi ativado para enviar mensagens',
            'detail' => 'O número já está na conta do WhatsApp, mas ainda não foi registrado na Meta para disparar mensagens.',
            'action' => 'Preencha o Código PIN no bloco "Conexão", aqui nesta tela, com os 6 dígitos da verificação em duas etapas, e salve.',
        ],
        [
            'needles' => ['message template', 'template is paused', 'template was paused'],
            'title' => 'Um modelo de mensagem foi pausado pela Meta',
            'detail' => 'Modelos que recebem muitas denúncias ou bloqueios de clientes são pausados automaticamente.',
            'action' => 'Reveja o texto na tela de Modelos e crie uma versão nova, mais próxima do que o cliente espera receber.',
        ],
        [
            'needles' => ['restricted', 'violation', 'violated', 'policy'],
            'title' => 'A Meta aplicou uma restrição nesta conta',
            'detail' => 'A conta foi restringida por descumprir as políticas do WhatsApp Business.',
            'action' => 'Abra o Gerenciador do WhatsApp da Meta, leia o aviso completo e envie um pedido de revisão.',
        ],
    ];

    /**
     * @return list<TranslatedReason>
     */
    public function forInstance(WhatsappInstance $instance): array
    {
        return $this->translate($instance->healthReasons());
    }

    /**
     * The drawer breaks reasons down per entity, so those need translating too —
     * otherwise the English survives exactly where the detail is read most.
     *
     * @param  array<int, mixed>  $entities
     * @return list<array{type: string, id: string, status: string, reasons: list<TranslatedReason>}>
     */
    public function translateEntities(array $entities): array
    {
        $translated = [];

        foreach ($entities as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $translated[] = [
                'type' => (string) ($entity['type'] ?? 'UNKNOWN'),
                'id' => (string) ($entity['id'] ?? ''),
                'status' => (string) ($entity['status'] ?? 'UNKNOWN'),
                'reasons' => $this->translate(
                    array_values(array_filter(
                        (array) ($entity['reasons'] ?? []),
                        is_string(...),
                    )),
                ),
            ];
        }

        return $translated;
    }

    /**
     * @param  list<string>  $reasons
     * @return list<TranslatedReason>
     */
    public function translate(array $reasons): array
    {
        $translated = [];

        foreach ($reasons as $reason) {
            $entry = $this->translateOne($reason);

            if ($entry !== null) {
                $translated[$entry['title']] = $entry;
            }
        }

        return array_values($translated);
    }

    /**
     * @return TranslatedReason|null Null when the message is not about messaging at all.
     */
    private function translateOne(string $reason): ?array
    {
        $needle = mb_strtolower(trim($reason));

        if ($needle === '') {
            return null;
        }

        foreach (self::IRRELEVANT as $irrelevant) {
            if (str_contains($needle, $irrelevant)) {
                return null;
            }
        }

        foreach (self::MATCHERS as $matcher) {
            foreach ($matcher['needles'] as $candidate) {
                if (str_contains($needle, $candidate)) {
                    return [
                        'title' => $matcher['title'],
                        'detail' => $matcher['detail'],
                        'action' => $matcher['action'],
                        'original' => null,
                    ];
                }
            }
        }

        // Meta adds phrasings without warning. Passing the English through reads
        // badly, but dropping it would hide the only explanation the user has —
        // `original` lets the UI mark it as Meta's own wording.
        return [
            'title' => 'A Meta enviou um aviso sobre esta conta',
            'detail' => trim($reason),
            'action' => null,
            'original' => trim($reason),
        ];
    }
}
