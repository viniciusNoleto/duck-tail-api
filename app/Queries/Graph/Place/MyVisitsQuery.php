<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class MyVisitsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.myVisit` em lote: a visita (`VISITED {date, rating}`) de `$user_id` a cada lugar; lugar sem
     * visita fica de fora.
     *
     * @param int $user_id
     * @param list<string> $ids
     * @return array<string, array{user: array<string, mixed>, date: string|null, rating: int|null}>
     */
    public function get(int $user_id, array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id
             MATCH (u:User {id: $user_id})-[v:VISITED]->(:Place {id: id})
             RETURN id, {user: ' . Cypher::user('u') . ', date: toString(v.date), rating: v.rating} AS visit',
            ['ids' => $ids, 'user_id' => $user_id]
        );

        return array_column($rows, 'visit', 'id');
    }
}
