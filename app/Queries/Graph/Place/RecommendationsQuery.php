<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class RecommendationsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * "Quem visitou isso também visitou…": lugares visitados por quem visitou algum lugar que `$user_id`
     * visitou, que ele ainda não visitou, ordenados por quantas pessoas diferentes levam até eles.
     * Usuário sem visitas (ou sem ninguém em comum) recebe `[]`.
     *
     * @param int $user_id
     * @param int $limit
     * @return list<array<string, mixed>>
     */
    public function get(int $user_id, int $limit): array {
        $rows = $this->graph->read(
            'MATCH (me:User {id: $user_id})-[:VISITED]->(:Place)<-[:VISITED]-(other:User)-[:VISITED]->(rec:Place)
             WHERE other <> me AND NOT (me)-[:VISITED]->(rec)
             WITH rec, count(DISTINCT other) AS score ORDER BY score DESC, rec.name, rec.id LIMIT $limit
             RETURN ' . Cypher::place('rec') . ' AS place',
            ['user_id' => $user_id, 'limit' => $limit]
        );

        return array_column($rows, 'place');
    }
}
