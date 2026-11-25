<?php

namespace App\Queries\Graph\User;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Enum\Graph\UserPlaceLinkEnum;

final class UserPlacesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `User.visited` / `User.wantsToVisit` em lote, do mais recente para o mais antigo.
     *
     * @param list<string> $user_ids ids do Postgres, como string (`ID`)
     * @param UserPlaceLinkEnum $link
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $user_ids, UserPlaceLinkEnum $link): array {
        $rows = $this->graph->read(
            "UNWIND \$ids AS id MATCH (:User {id: toInteger(id)})-[r:{$link->value}]->(p:Place)
             WITH id, p, r ORDER BY coalesce(r.date, r.since) DESC, p.name
             RETURN id, collect(" . Cypher::place('p') . ') AS places',
            ['ids' => $user_ids]
        );

        return array_column($rows, 'places', 'id');
    }
}
