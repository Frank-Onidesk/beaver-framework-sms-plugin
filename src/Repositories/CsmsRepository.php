<?php

/**
 * ⚠️ STUB — em memória, não persiste.
 *
 * O Bloco 4 (ORM/migrations) do Beaver ainda não está pronto.
 * Em vez de bloquear o plugin à espera dele, emulo aqui a parte
 * em falta com um array estático. A assinatura (find/create/update)
 * é a mesma que a versão real vai ter — quando o ORM existir,
 * troco só o corpo destes métodos e o controller não muda.
 */

namespace Beaver\Plugins\Sms\Repositories;

class CsmsRepository
{
    /** @var array<string, array<string, mixed>> */
    private static array $store = [];

    public function __construct()
    {
        error_log('[SMS] CsmsRepository STUB em uso — não persiste!');
    }

    public function find(string $id): ?array
    {
        return self::$store[$id] ?? null;
    }

    public function create(array $attrs): array
    {
        $id = bin2hex(random_bytes(8));

        $attrs['id']        = $id;
        $attrs['createdAt'] = date('Y-m-d H:i:s');

        self::$store[$id] = $attrs;

        return $attrs;
    }

/**
 * Atualiza o registo em memória e devolve-o por referência.
 *
 * Atenção: $record é passado por referência — depois desta chamada,
 * a variável do chamador já reflete os novos valores. Não é preciso
 * reatribuir.
 */
    public function update(array &$record, array $attrs): void
    {
        $record = array_merge($record, $attrs);
        self::$store[$record['id']] = $record;
    }

    public function all(): array
    {
        return array_values(self::$store);
    }
}
