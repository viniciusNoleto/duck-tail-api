<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Data\Graph\Location\ListLocationsDTO;

final class ListLocationsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `locations(parentId, kind, limit)`: filhas diretas de `parentId`; sem ele, todas as do `kind` (em
     * qualquer nível) ou, sem os dois, as raízes. Da que tem mais lugares (contando as de baixo) para a que
     * tem menos, depois por nome.
     *
     * @param ListLocationsDTO $dto
     * @return list<array<string, mixed>>
     */
    public function get(ListLocationsDTO $dto): array {
        $rows = $this->graph->read(
            'MATCH (l:Location)
             WHERE ($kind IS NULL OR l.kind = $kind)
               AND CASE WHEN $parent IS NOT NULL THEN EXISTS { (l)-[:WITHIN]->(:Location {id: $parent}) }
                        WHEN $kind IS NOT NULL THEN true
                        ELSE NOT (l)-[:WITHIN]->(:Location) END
             CALL (l) {
                 OPTIONAL MATCH (p:Place)-[:LOCATED_IN]->(:Location)-[:WITHIN*0..8]->(l)
                 RETURN count(DISTINCT p) AS places
             }
             WITH l, places ORDER BY places DESC, l.name, l.id LIMIT $limit
             RETURN ' . Cypher::location('l') . ' AS location',
            ['parent' => $dto->parent_id, 'kind' => $dto->kind, 'limit' => $dto->limit]
        );

        return array_column($rows, 'location');
    }
}
