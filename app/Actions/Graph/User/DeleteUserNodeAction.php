<?php

namespace App\Actions\Graph\User;

use App\Base\Graph\Graph;

final class DeleteUserNodeAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Remove o nó `User`, os roteiros que ele criou e todas as relações (visitas, quem segue, quem o
     * segue). Os lugares ficam.
     *
     * @param int $user_id
     * @return void
     */
    public function handle(int $user_id): void {
        $this->graph->write(
            'MATCH (u:User {id: $id})
             OPTIONAL MATCH (u)-[:CREATED]->(i:Itinerary)
             DETACH DELETE i, u',
            ['id' => $user_id]
        );
    }
}
