<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class FindLocationQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * @param string $id
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array {
        $rows = $this->graph->read('MATCH (l:Location {id: $id}) RETURN ' . Cypher::location('l') . ' AS location', ['id' => $id]);

        return $rows[0]['location'] ?? null;
    }
}
