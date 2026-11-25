<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Graph;

final class LocationPlaceCountsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Location.placeCount` em lote: lugares da localidade e de todas as de baixo.
     *
     * @param list<string> $ids
     * @return array<string, int>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (l:Location {id: id})
             OPTIONAL MATCH (p:Place)-[:LOCATED_IN]->(:Location)-[:WITHIN*0..8]->(l)
             RETURN id, count(DISTINCT p) AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
