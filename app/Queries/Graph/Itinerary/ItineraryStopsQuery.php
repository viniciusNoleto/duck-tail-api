<?php

namespace App\Queries\Graph\Itinerary;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class ItineraryStopsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Itinerary.stops` em lote, por dia e ordem.
     *
     * @param list<string> $ids
     * @return array<string, list<array{place: array<string, mixed>, day: int, order: int}>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Itinerary {id: id})-[s:INCLUDES]->(p:Place)
             WITH id, p, s ORDER BY s.day, s.order
             RETURN id, collect({place: ' . Cypher::place('p') . ', day: s.day, order: s.order}) AS stops',
            ['ids' => $ids]
        );

        return array_column($rows, 'stops', 'id');
    }
}
