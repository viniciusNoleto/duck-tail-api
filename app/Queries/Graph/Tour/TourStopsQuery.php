<?php

namespace App\Queries\Graph\Tour;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class TourStopsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Tour.stops` em lote (`STOPS_AT {order}`), na ordem do passeio.
     *
     * @param list<string> $ids
     * @return array<string, list<array{order: int, place: array<string, mixed>}>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Tour {id: id})-[s:STOPS_AT]->(p:Place)
             WITH id, s, p ORDER BY s.order
             RETURN id, collect({order: s.order, place: ' . Cypher::place('p') . '}) AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
