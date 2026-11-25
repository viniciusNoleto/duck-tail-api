<?php

namespace App\Actions\Graph\Schema;

use App\Base\Graph\Graph;
use App\Base\Graph\GraphSchema;

final class ApplyGraphSchemaAction {

    public const AWAIT_INDEXES_SECONDS = 300;

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Cria as constraints e os índices de `GraphSchema` que ainda não existem e espera os índices ficarem
     * online (índice ainda populando deixaria a busca de texto vazia logo depois do deploy).
     * Cada comando de schema roda na própria transação (o Neo4j não mistura schema e dados).
     *
     * @return list<string> os comandos executados
     */
    public function handle(): array {
        $statements = GraphSchema::statements();

        foreach ($statements as $statement) {
            $this->graph->write($statement);
        }

        $this->graph->write('CALL db.awaitIndexes($seconds)', ['seconds' => self::AWAIT_INDEXES_SECONDS]);

        return $statements;
    }
}
