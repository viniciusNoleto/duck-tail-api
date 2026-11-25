<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class LocationParentsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Location.parent` em lote.
     *
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Location {id: id})-[:WITHIN]->(a:Location) RETURN id, ' . Cypher::location('a') . ' AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
