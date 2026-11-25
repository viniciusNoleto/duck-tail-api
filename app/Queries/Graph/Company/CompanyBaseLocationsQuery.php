<?php

namespace App\Queries\Graph\Company;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class CompanyBaseLocationsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Company.baseLocation` em lote (`BASED_IN`).
     *
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Company {id: id})-[:BASED_IN]->(l:Location) RETURN id, ' . Cypher::location('l') . ' AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
