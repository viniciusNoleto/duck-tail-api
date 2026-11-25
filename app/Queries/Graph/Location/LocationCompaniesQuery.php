<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class LocationCompaniesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Location.companies` em lote: sediadas (`BASED_IN`) ou que atendem (`SERVES`) a localidade ou uma de baixo, por nome, até 50.
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (l:Location {id: id})
             CALL (l) {
                 MATCH (co:Company)-[:BASED_IN|SERVES]->(:Location)-[:WITHIN*0..8]->(l)
                 WITH DISTINCT co ORDER BY co.name LIMIT 50
                 RETURN collect(' . Cypher::company('co') . ') AS companies
             }
             RETURN id, companies AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
