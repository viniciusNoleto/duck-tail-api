<?php

namespace App\Actions\Graph\User;

use App\Base\Graph\Graph;
use App\Models\User;

final class ReconcileUserNodesAction {

    public const BATCH_SIZE = 500;

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Rede de segurança do `UserObserver` (`ducktail:sync-users`): cria/renomeia o nó `User` de todo
     * usuário do Postgres e apaga os nós (com roteiros) de usuários que não existem mais.
     *
     * @return array{synced: int, removed: int}
     */
    public function handle(): array {
        $synced = 0;
        $ids = [];

        User::query()->select(['id', 'name'])->orderBy('id')->chunk(self::BATCH_SIZE, function ($users) use (&$synced, &$ids) {
            $rows = $users->map(fn(User $user) => ['id' => $user->id, 'name' => $user->name])->all();
            $this->graph->write('UNWIND $users AS row MERGE (u:User {id: row.id}) SET u.name = row.name', ['users' => $rows]);
            $synced += count($rows);
            array_push($ids, ...array_column($rows, 'id'));
        });

        $removed = $this->graph->write(
            'MATCH (u:User) WHERE NOT u.id IN $ids
             OPTIONAL MATCH (u)-[:CREATED]->(i:Itinerary)
             WITH collect(DISTINCT u) AS users, collect(DISTINCT i) AS itineraries
             FOREACH (i IN itineraries | DETACH DELETE i)
             FOREACH (u IN users | DETACH DELETE u)
             RETURN size(users) AS removed',
            ['ids' => $ids]
        );

        return ['synced' => $synced, 'removed' => (int)($removed[0]['removed'] ?? 0)];
    }
}
