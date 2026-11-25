<?php

namespace App\Queries\Graph\User;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class UserItinerariesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `User.itineraries` em lote: o dono vê todos os roteiros; os outros (e anônimos), só os públicos.
     *
     * @param list<string> $user_ids
     * @param int|null $viewer_id usuário da sessão
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(array $user_ids, ?int $viewer_id): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (u:User {id: toInteger(id)})-[:CREATED]->(i:Itinerary)
             WHERE i.isPublic OR u.id = $viewer_id
             WITH id, i ORDER BY i.createdAt DESC
             RETURN id, collect(' . Cypher::itinerary('i') . ') AS itineraries',
            ['ids' => $user_ids, 'viewer_id' => $viewer_id]
        );

        return array_column($rows, 'itineraries', 'id');
    }
}
