<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class LocationCategoriesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Location.categories` em lote: as categorias dos lugares da localidade e das de baixo, da que tem
     * mais lugares ali para a que tem menos (empate por nome), até 50.
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (l:Location {id: id})
             CALL (l) {
                 MATCH (p:Place)-[:LOCATED_IN]->(:Location)-[:WITHIN*0..8]->(l)
                 WITH DISTINCT p
                 MATCH (p)-[:HAS_CATEGORY]->(c:Category)
                 WITH c, count(p) AS places ORDER BY places DESC, c.name LIMIT 50
                 RETURN collect(' . Cypher::category('c') . ') AS categories
             }
             RETURN id, categories AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
