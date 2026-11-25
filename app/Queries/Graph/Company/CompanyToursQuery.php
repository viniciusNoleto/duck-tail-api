<?php

namespace App\Queries\Graph\Company;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class CompanyToursQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Company.tours` em lote (`OFFERS`), por nome.
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Company {id: id})-[:OFFERS]->(t:Tour)
             WITH id, t ORDER BY t.name
             RETURN id, collect(' . Cypher::tour('t') . ') AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
