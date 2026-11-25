<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class PlaceCategoriesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.categories` em lote (`HAS_CATEGORY`), por nome.
     *
     * @param list<string> $ids
     * @return array<string, list<array{id: string, name: string}>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Place {id: id})-[:HAS_CATEGORY]->(c:Category)
             WITH id, c ORDER BY c.name
             RETURN id, collect(' . Cypher::category('c') . ') AS categories',
            ['ids' => $ids]
        );

        return array_column($rows, 'categories', 'id');
    }
}
