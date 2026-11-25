<?php

namespace App\Queries\Graph\Tour;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class FindTourQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * @param string $id
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array {
        $rows = $this->graph->read('MATCH (t:Tour {id: $id}) RETURN ' . Cypher::tour('t') . ' AS tour', ['id' => $id]);

        return $rows[0]['tour'] ?? null;
    }
}
