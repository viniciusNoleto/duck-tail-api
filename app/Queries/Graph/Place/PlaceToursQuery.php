<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class PlaceToursQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.tours` em lote: passeios com parada no lugar, por nome.
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (t:Tour)-[:STOPS_AT]->(:Place {id: id})
             WITH id, t ORDER BY t.name
             RETURN id, collect(' . Cypher::tour('t') . ') AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
