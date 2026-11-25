<?php

namespace App\Observers;

use App\Actions\Graph\User\DeleteUserNodeAction;
use App\Actions\Graph\User\SyncUserNodeAction;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mantém o nó `User` do grafo espelhando o Postgres: cria/renomeia ao gravar, apaga (com os roteiros e
 * relações) ao excluir.
 *
 * Roda **depois do commit** (`ShouldHandleEventsAfterCommit`): um usuário cuja transação voltou atrás
 * nunca chega ao grafo. Falha no Neo4j **não** derruba a operação do Postgres (aprovar cadastro, excluir
 * usuário): vira `Log::warning('graph.user_sync_failed')`. As mutations recriam o nó com `MERGE` e
 * `php artisan ducktail:sync-users` reconcilia tudo; o nó que sobrar de um usuário excluído só é
 * removido por esse comando.
 */
class UserObserver implements ShouldHandleEventsAfterCommit {

    public function __construct(
        private readonly SyncUserNodeAction   $syncUserNode,
        private readonly DeleteUserNodeAction $deleteUserNode,
    ) {
    }

    public function saved(User $user): void {
        if (!$user->wasRecentlyCreated && !$user->wasChanged('name')) {
            return;
        }

        $this->safely('saved', $user->id, fn() => $this->syncUserNode->handle($user));
    }

    public function deleted(User $user): void {
        $this->safely('deleted', $user->id, fn() => $this->deleteUserNode->handle($user->id));
    }

    /**
     * @param string $event
     * @param int $user_id
     * @param \Closure(): void $sync
     * @return void
     */
    private function safely(string $event, int $user_id, \Closure $sync): void {
        try {
            $sync();
        } catch (Throwable $exception) {
            Log::warning('graph.user_sync_failed', [
                'event' => $event,
                'user_id' => $user_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
