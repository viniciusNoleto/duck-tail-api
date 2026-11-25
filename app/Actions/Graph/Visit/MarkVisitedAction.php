<?php

namespace App\Actions\Graph\Visit;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Data\Graph\Visit\MarkVisitedDTO;
use App\Exceptions\ApiException;
use App\Models\User;

final class MarkVisitedAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `(User)-[:VISITED {date, rating}]->(Place)`: a data é o dia de hoje no fuso do negócio; marcar de
     * novo atualiza data e nota (uma relação por par). Sai da lista "quero visitar". O nó `User` é
     * garantido aqui (`MERGE`): se a sincronização do observer falhou, a mutation não depende dela.
     *
     * @param MarkVisitedDTO $dto
     * @param User $user
     * @return array<string, mixed> o lugar
     * @throws ApiException lugar inexistente (404)
     */
    public function handle(MarkVisitedDTO $dto, User $user): array {
        $rows = $this->graph->write(
            'MATCH (p:Place {id: $place_id})
             MERGE (u:User {id: $user_id}) ON CREATE SET u.name = $user_name
             MERGE (u)-[v:VISITED]->(p)
             SET v.date = date($today), v.rating = $rating
             WITH u, p
             OPTIONAL MATCH (u)-[w:WANTS_TO_VISIT]->(p)
             DELETE w
             RETURN ' . Cypher::place('p') . ' AS place',
            [
                'place_id' => $dto->place_id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'today' => business_today(),
                'rating' => $dto->rating,
            ]
        );

        return $rows[0]['place'] ?? throw new ApiException('messages.places.not_found', 404);
    }
}
