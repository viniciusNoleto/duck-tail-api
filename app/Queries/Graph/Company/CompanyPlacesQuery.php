<?php

namespace App\Queries\Graph\Company;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class CompanyPlacesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Company.places` em lote: onde opera (`OPERATES_AT`) ou por onde passam os seus passeios, por nome.
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (co:Company {id: id})
             CALL (co) {
                 MATCH (co)-[:OPERATES_AT]->(p:Place) RETURN p
                 UNION
                 MATCH (co)-[:OFFERS]->(:Tour)-[:STOPS_AT]->(p:Place) RETURN p
             }
             WITH id, p ORDER BY p.name
             RETURN id, collect(' . Cypher::place('p') . ') AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
