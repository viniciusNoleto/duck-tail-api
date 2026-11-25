<?php

namespace App\Queries\Graph\Theme;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class ThemePlacesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Theme.places` em lote, pelo nome do tema (a chave do `Theme`).
     *
     * @param list<string> $names
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $names): array {
        $rows = $this->graph->read(
            'UNWIND $names AS name MATCH (p:Place)-[:PART_OF]->(:Theme {name: name})
             WITH name, p ORDER BY p.name
             RETURN name, collect(' . Cypher::place('p') . ') AS places',
            ['names' => $names]
        );

        return array_column($rows, 'places', 'name');
    }
}
