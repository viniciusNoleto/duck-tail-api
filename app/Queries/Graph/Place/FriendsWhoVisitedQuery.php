<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class FriendsWhoVisitedQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.friendsWhoVisited` em lote: quem `$user_id` segue (`FOLLOWS`) e visitou cada lugar, da visita
     * mais recente para a mais antiga.
     *
     * @param int $user_id
     * @param list<string> $ids
     * @return array<string, list<array{user: array<string, mixed>, date: string|null, rating: int|null}>>
     */
    public function get(int $user_id, array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id
             MATCH (:User {id: $user_id})-[:FOLLOWS]->(f:User)-[v:VISITED]->(:Place {id: id})
             WITH id, f, v ORDER BY v.date DESC, f.name
             RETURN id, collect({user: ' . Cypher::user('f') . ', date: toString(v.date), rating: v.rating}) AS visits',
            ['ids' => $ids, 'user_id' => $user_id]
        );

        return array_column($rows, 'visits', 'id');
    }
}
