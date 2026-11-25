<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class PlaceCompaniesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.companies` em lote: empresas que operam no lugar (`OPERATES_AT`) ou têm passeio com parada nele, por nome.
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (p:Place {id: id})
             CALL (p) {
                 MATCH (co:Company)-[:OPERATES_AT]->(p) RETURN co
                 UNION
                 MATCH (co:Company)-[:OFFERS]->(:Tour)-[:STOPS_AT]->(p) RETURN co
             }
             WITH id, co ORDER BY co.name
             RETURN id, collect(' . Cypher::company('co') . ') AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
