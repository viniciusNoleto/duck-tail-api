<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class LocationToursQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Location.tours` em lote: passeios com alguma parada na localidade ou numa de baixo, por nome, até 50.
     *
     * @param list<string> $ids
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (l:Location {id: id})
             CALL (l) {
                 MATCH (t:Tour)-[:STOPS_AT]->(:Place)-[:LOCATED_IN]->(:Location)-[:WITHIN*0..8]->(l)
                 WITH DISTINCT t ORDER BY t.name LIMIT 50
                 RETURN collect(' . Cypher::tour('t') . ') AS tours
             }
             RETURN id, tours AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
