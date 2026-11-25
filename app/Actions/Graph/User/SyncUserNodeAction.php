<?php

namespace App\Actions\Graph\User;

use App\Base\Graph\Graph;
use App\Models\User;

final class SyncUserNodeAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Cria ou atualiza o nó `User` espelho (mesmo `id` do Postgres, só o nome: e-mail e senha não vão
     * para o grafo). Idempotente (`MERGE`).
     *
     * @param User $user
     * @return void
     */
    public function handle(User $user): void {
        $this->graph->write('MERGE (u:User {id: $id}) SET u.name = $name', ['id' => $user->id, 'name' => $user->name]);
    }
}
