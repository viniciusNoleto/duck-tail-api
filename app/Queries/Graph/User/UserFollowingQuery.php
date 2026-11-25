<?php

namespace App\Queries\Graph\User;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class UserFollowingQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `User.following` em lote, por nome.
     *
     * @param list<string> $user_ids
     * @return array<string, list<array{id: string, name: string}>>
     */
    public function get(array $user_ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (:User {id: toInteger(id)})-[:FOLLOWS]->(f:User)
             WITH id, f ORDER BY f.name
             RETURN id, collect(' . Cypher::user('f') . ') AS following',
            ['ids' => $user_ids]
        );

        return array_column($rows, 'following', 'id');
    }
}
