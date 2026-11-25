<?php

namespace App\Actions\Graph\Seed;

use App\Base\Graph\Graph;

final class AssignSeedIdsAction {

    /** Rótulos cujo id vai para a URL do web. */
    public const LABELS = ['Location', 'Place', 'Company', 'Tour'];

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Troca o id legível dos nós de exemplo (`seed-…`, o slug do nome) pelo id opaco de `seed_id()`.
     * Os seeders já gravam o UUID; este passo cobre o grafo semeado antes disso e roda no
     * `ducktail:graph-migrate` e nos seeders. Idempotente: nó sem `seed-` não muda.
     *
     * @return int quantos nós receberam id
     */
    public function handle(): int {
        $total = 0;

        foreach (self::LABELS as $label) {
            $ids = array_column($this->graph->read(
                "MATCH (n:{$label}) WHERE n.id STARTS WITH 'seed-' RETURN n.id AS id"
            ), 'id');

            if ($ids === []) {
                continue;
            }

            $this->graph->write(
                "UNWIND \$rows AS row MATCH (n:{$label} {id: row.old}) SET n.id = row.new",
                ['rows' => array_map(fn(string $id) => ['old' => $id, 'new' => seed_id($id)], $ids)]
            );
            $total += count($ids);
        }

        return $total;
    }
}
