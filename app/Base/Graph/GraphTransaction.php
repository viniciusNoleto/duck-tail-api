<?php

namespace App\Base\Graph;

use Laudis\Neo4j\Contracts\TransactionInterface;

/**
 * Transação de escrita aberta por `Graph::transaction()`: o mesmo `run()` de `Graph`, com o resultado já
 * convertido em arrays.
 */
final class GraphTransaction {

    public function __construct(
        private readonly TransactionInterface $transaction,
    ) {
    }

    /**
     * @param string $cypher
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function run(string $cypher, array $parameters = []): array {
        return Graph::rows($this->transaction->run($cypher, $parameters));
    }
}
