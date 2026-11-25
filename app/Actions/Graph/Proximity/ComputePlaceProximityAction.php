<?php

namespace App\Actions\Graph\Proximity;

use App\Base\Graph\Graph;

final class ComputePlaceProximityAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Recalcula `(Place)-[:NEAR {distanceM}]->(Place)` para todo par a até `$radius_m`: apaga as arestas
     * antigas (o raio pode ter mudado) e grava uma por par (do menor `id` para o maior; as leituras usam
     * `NEAR` sem direção). Usa o índice de ponto (~N buscas, não N²) e grava em lotes de lugares, cada um
     * na sua transação (milhares de lugares numa transação só estouram a memória de transação do Neo4j).
     *
     * @param float $radius_m
     * @return int arestas gravadas
     */
    public function handle(float $radius_m): int {
        $this->graph->writeUntilDone('MATCH (:Place)-[n:NEAR]->(:Place) WITH n LIMIT $limit DELETE n RETURN count(*) AS affected');

        $ids = array_column($this->graph->read('MATCH (p:Place) RETURN p.id AS id ORDER BY id'), 'id');

        return $this->graph->writeInBatches(
            'UNWIND $keys AS id MATCH (a:Place {id: id})
             CALL (a) {
                 MATCH (b:Place) WHERE a.id < b.id AND point.distance(a.location, b.location) <= $radius
                 MERGE (a)-[n:NEAR]->(b)
                 SET n.distanceM = point.distance(a.location, b.location)
                 RETURN count(n) AS edges
             }
             RETURN sum(edges) AS affected',
            $ids,
            ['radius' => $radius_m]
        );
    }
}
