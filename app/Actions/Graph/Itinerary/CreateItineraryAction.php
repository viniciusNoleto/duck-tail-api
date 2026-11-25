<?php

namespace App\Actions\Graph\Itinerary;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Base\Graph\GraphTransaction;
use App\Data\Graph\Itinerary\CreateItineraryDTO;
use App\Data\Graph\Itinerary\ItineraryStopDTO;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Str;

final class CreateItineraryAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `(User)-[:CREATED]->(Itinerary)-[:INCLUDES {day, order}]->(Place)`, tudo numa transação: se algum
     * lugar não existe, nada é gravado (422 `itineraries.invalid_place`).
     *
     * @param CreateItineraryDTO $dto
     * @param User $user
     * @return array<string, mixed> o roteiro (`id`, `title`, `isPublic`)
     * @throws ApiException
     */
    public function handle(CreateItineraryDTO $dto, User $user): array {
        return $this->graph->transaction(function (GraphTransaction $transaction) use ($dto, $user) {
            $rows = $transaction->run(
                'MERGE (u:User {id: $user_id}) ON CREATE SET u.name = $user_name
                 CREATE (u)-[:CREATED]->(i:Itinerary {id: $id, title: $title, isPublic: $is_public, createdAt: datetime()})
                 WITH i
                 UNWIND $stops AS stop
                 MATCH (p:Place {id: stop.place_id})
                 CREATE (i)-[:INCLUDES {day: stop.day, order: stop.order}]->(p)
                 WITH i, count(*) AS linked
                 RETURN ' . Cypher::itinerary('i') . ' AS itinerary, linked',
                [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'id' => (string)Str::uuid(),
                    'title' => $dto->title,
                    'is_public' => $dto->is_public,
                    'stops' => array_map(fn(ItineraryStopDTO $stop) => $stop->toArray(), $dto->stops),
                ]
            );

            // Lançar aqui desfaz a transação inteira (roteiro, relação com o usuário e paradas já criadas).
            if (($rows[0]['linked'] ?? 0) !== count($dto->stops)) {
                throw new ApiException('messages.itineraries.invalid_place', 422);
            }

            return $rows[0]['itinerary'];
        });
    }
}
