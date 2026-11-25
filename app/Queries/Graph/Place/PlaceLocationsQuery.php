<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class PlaceLocationsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.location` em lote (`LOCATED_IN`).
     *
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Place {id: id})-[:LOCATED_IN]->(l:Location) RETURN id, ' . Cypher::location('l') . ' AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
