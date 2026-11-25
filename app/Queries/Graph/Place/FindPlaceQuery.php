<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class FindPlaceQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * @param string $id
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array {
        $rows = $this->graph->read('MATCH (p:Place {id: $id}) RETURN ' . Cypher::place('p') . ' AS place', ['id' => $id]);

        return $rows[0]['place'] ?? null;
    }
}
