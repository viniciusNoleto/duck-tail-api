<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Graph;

final class PlaceThemesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.themes` em lote (`PART_OF`), por nome.
     *
     * @param list<string> $ids
     * @return array<string, list<array{name: string, description: string|null}>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:Place {id: id})-[:PART_OF]->(t:Theme)
             WITH id, t ORDER BY t.name
             RETURN id, collect(t {.name, .description}) AS themes',
            ['ids' => $ids]
        );

        return array_column($rows, 'themes', 'id');
    }
}
