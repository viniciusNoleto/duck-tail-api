<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class LocationAncestorsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Location.ancestors` em lote: da raiz até o pai (`WITHIN` para cima).
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Location {id: id})-[w:WITHIN*1..8]->(a:Location)
             WITH id, a, size(w) AS depth ORDER BY depth DESC
             RETURN id, collect(' . Cypher::location('a') . ') AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
