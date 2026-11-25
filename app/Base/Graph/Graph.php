<?php

namespace App\Base\Graph;

use Closure;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use Throwable;

/**
 * Único ponto de acesso ao Neo4j. Não é um repositório: não conhece o domínio, só executa Cypher e
 * devolve **arrays PHP simples** (cada linha é `['coluna' => valor]`, mapas e listas já convertidos).
 * O Cypher de leitura fica nas classes `App\Queries\Graph\…` e o de escrita nas `Action`s.
 *
 * Convenção: o Cypher projeta só tipos primitivos (`p {.id, .name, latitude: p.location.latitude}`,
 * `toString(v.date)`), nunca nós ou pontos crus, para o resultado ser serializável sem conversão.
 */
final class Graph {

    public function __construct(
        private readonly ClientInterface $client,
    ) {
    }

    /**
     * Leitura numa transação de leitura (o driver repete erros transitórios sozinho).
     *
     * @param string $cypher
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function read(string $cypher, array $parameters = []): array {
        return $this->client->readTransaction(
            fn(TransactionInterface $transaction) => self::rows($transaction->run($cypher, $parameters))
        );
    }

    /**
     * Uma escrita (um comando) numa transação de escrita própria.
     *
     * @param string $cypher
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function write(string $cypher, array $parameters = []): array {
        return $this->transaction(fn(GraphTransaction $transaction) => $transaction->run($cypher, $parameters));
    }

    /**
     * Várias escritas tudo-ou-nada, o `DB::transaction(…, attempts)` do grafo: qualquer exceção dentro do
     * callback (inclusive uma `ApiException` de regra) faz **rollback explícito** e sobe; erro transitório
     * do Neo4j (`TransientError`: deadlock entre transações, lock que não saiu) repete o callback inteiro
     * até `neo4j.transaction_attempts` vezes, então ele não pode ter efeito externo.
     *
     * Não usa o `writeTransaction` do driver: na versão 3.x ele relança exceções que não são do Neo4j sem
     * desfazer a transação (fica aberta na conexão) e repete o transitório na mesma transação já abortada.
     *
     * @template T
     * @param Closure(GraphTransaction): T $callback
     * @return T
     * @throws Throwable
     */
    public function transaction(Closure $callback): mixed {
        $attempts = max(1, (int)config('neo4j.transaction_attempts'));

        for ($attempt = 1; ; $attempt++) {
            $transaction = $this->client->beginTransaction();

            try {
                $result = $callback(new GraphTransaction($transaction));
                $transaction->commit();

                return $result;
            } catch (Throwable $exception) {
                self::rollbackQuietly($transaction);

                if ($attempt >= $attempts || !self::isTransient($exception)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param Throwable $exception
     * @return bool
     */
    public static function isTransient(Throwable $exception): bool {
        return $exception instanceof Neo4jException && $exception->getClassification() === 'TransientError';
    }

    /**
     * O rollback de uma transação que o servidor já abortou também falha: o erro original é o que importa.
     *
     * @param UnmanagedTransactionInterface $transaction
     * @return void
     */
    private static function rollbackQuietly(UnmanagedTransactionInterface $transaction): void {
        try {
            if ($transaction->isFinished() === false) {
                $transaction->rollback();
            }
        } catch (Throwable) {
            // já abortada pelo servidor
        }
    }

    /**
     * Escrita em massa em lotes, cada lote na sua transação: `$cypher` recebe `$keys` (um pedaço de
     * `$keys`) e devolve uma coluna `affected`. O Neo4j limita a memória de uma transação
     * (`dbms.memory.transaction.total.max`): recalcular as arestas de milhares de lugares numa transação só
     * estoura esse limite. Devolve a soma de `affected`.
     *
     * @param string $cypher
     * @param list<mixed> $keys
     * @param array<string, mixed> $parameters
     * @param int $size
     * @return int
     */
    public function writeInBatches(string $cypher, array $keys, array $parameters = [], int $size = 200): int {
        $affected = 0;

        foreach (array_chunk($keys, max(1, $size)) as $chunk) {
            $rows = $this->write($cypher, ['keys' => $chunk] + $parameters);
            $affected += (int)($rows[0]['affected'] ?? 0);
        }

        return $affected;
    }

    /**
     * Repete uma escrita limitada (`… WITH x LIMIT $limit DELETE x RETURN count(*) AS affected`) até ela não
     * afetar mais nada: apagar milhares de arestas sem uma transação gigante.
     *
     * @param string $cypher
     * @param array<string, mixed> $parameters
     * @param int $limit
     * @return int total afetado
     */
    public function writeUntilDone(string $cypher, array $parameters = [], int $limit = 5000): int {
        $total = 0;

        do {
            $affected = (int)($this->write($cypher, ['limit' => $limit] + $parameters)[0]['affected'] ?? 0);
            $total += $affected;
        } while ($affected > 0);

        return $total;
    }

    /**
     * Conectividade (healthcheck `/up`): lança exceção se o Neo4j não responder.
     *
     * @return void
     */
    public function ping(): void {
        $this->read('RETURN 1 AS ok');
    }

    /**
     * @param SummarizedResult $result
     * @return list<array<string, mixed>>
     */
    public static function rows(SummarizedResult $result): array {
        /** @var list<array<string, mixed>> $rows */
        $rows = $result->toRecursiveArray();

        return $rows;
    }
}
