<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class LocationChildrenQuery {

    public const LIMIT = 200;

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Location.children` em lote: filhas diretas, da que tem mais lugares (contando as de baixo) para a
     * que tem menos, depois por nome. Até 200 por localidade.
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (parent:Location {id: id})
             CALL (parent) {
                 MATCH (c:Location)-[:WITHIN]->(parent)
                 CALL (c) {
                     OPTIONAL MATCH (p:Place)-[:LOCATED_IN]->(:Location)-[:WITHIN*0..8]->(c)
                     RETURN count(DISTINCT p) AS places
                 }
                 WITH c, places ORDER BY places DESC, c.name, c.id LIMIT $limit
                 RETURN collect(' . Cypher::location('c') . ') AS children
             }
             RETURN id, children AS value',
            ['ids' => $ids, 'limit' => self::LIMIT]
        );

        return array_column($rows, 'value', 'id');
    }
}
