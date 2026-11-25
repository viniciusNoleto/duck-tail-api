<?php

namespace App\Actions\Graph\Visit;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Exceptions\ApiException;
use App\Models\User;

final class RemoveVisitRatingAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Tira a nota de `(User)-[:VISITED]->(Place)`: a visita (e a data) continua. Idempotente (sem visita
     * ou sem nota, só devolve o lugar).
     *
     * @param string $place_id
     * @param User $user
     * @return array<string, mixed> o lugar
     * @throws ApiException lugar inexistente (404)
     */
    public function handle(string $place_id, User $user): array {
        $rows = $this->graph->write(
            'MATCH (p:Place {id: $place_id})
             OPTIONAL MATCH (:User {id: $user_id})-[v:VISITED]->(p)
             SET v.rating = null
             RETURN ' . Cypher::place('p') . ' AS place',
            ['place_id' => $place_id, 'user_id' => $user->id]
        );

        return $rows[0]['place'] ?? throw new ApiException('messages.places.not_found', 404);
    }
}
