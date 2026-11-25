<?php

namespace App\Actions\Graph\Visit;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Exceptions\ApiException;
use App\Models\User;

final class WantToVisitAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `(User)-[:WANTS_TO_VISIT {since}]->(Place)`, idempotente (pedir de novo não duplica nem muda `since`).
     *
     * @param string $place_id
     * @param User $user
     * @return array<string, mixed> o lugar
     * @throws ApiException lugar inexistente (404)
     */
    public function handle(string $place_id, User $user): array {
        $rows = $this->graph->write(
            'MATCH (p:Place {id: $place_id})
             MERGE (u:User {id: $user_id}) ON CREATE SET u.name = $user_name
             MERGE (u)-[w:WANTS_TO_VISIT]->(p) ON CREATE SET w.since = date($today)
             RETURN ' . Cypher::place('p') . ' AS place',
            ['place_id' => $place_id, 'user_id' => $user->id, 'user_name' => $user->name, 'today' => business_today()]
        );

        return $rows[0]['place'] ?? throw new ApiException('messages.places.not_found', 404);
    }
}
