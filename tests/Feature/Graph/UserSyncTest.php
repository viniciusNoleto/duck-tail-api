<?php

use App\Actions\Graph\User\SyncUserNodeAction;
use App\Base\Graph\Graph;
use App\Models\Auth\RegistrationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Contracts\ClientInterface;

/*
| O nó `User` do grafo espelha o Postgres (`UserObserver`, depois do commit).
*/

it('creates the graph user when a registration is approved, with the same id and only the name', function () {
    $request = RegistrationRequest::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);
    $admin = User::factory()->admin()->create();

    $this->withToken(accessToken($admin))->postJson("/api/registration-requests/{$request->id}/approve")->assertCreated();

    $user = User::query()->where('email', 'ana@example.com')->firstOrFail();

    $node = graphRow('MATCH (u:User {id: $id}) RETURN u.name AS name, keys(u) AS keys', ['id' => $user->id]);
    sort($node['keys']);

    expect($node)->toBe(['name' => 'Ana', 'keys' => ['id', 'name']]);
});

it('renames the graph user and ignores saves that do not change the name', function () {
    $user = User::factory()->create(['name' => 'Ana']);
    graph()->write('MATCH (u:User {id: $id}) SET u.marker = 1', ['id' => $user->id]);

    $user->update(['password' => 'another-secret']);
    expect(graphRow('MATCH (u:User {id: $id}) RETURN u.name AS name, u.marker AS marker', ['id' => $user->id]))->toBe(['name' => 'Ana', 'marker' => 1]);

    $user->update(['name' => 'Ana Maria']);
    expect(graphRow('MATCH (u:User {id: $id}) RETURN u.name AS name', ['id' => $user->id]))->toBe(['name' => 'Ana Maria']);
});

it('removes the graph user, with their itineraries and relationships, when the user is deleted', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    graph()->write(
        'MATCH (u:User {id: $id}), (f:User {id: $friend})
         CREATE (u)-[:FOLLOWS]->(f), (u)-[:CREATED]->(:Itinerary {id: "it-1", title: "x", isPublic: false})',
        ['id' => $user->id, 'friend' => $friend->id]
    );

    $this->withToken(accessToken(User::factory()->admin()->create()))->deleteJson("/api/users/{$user->id}")->assertOk();

    expect(graphRow('MATCH (u:User {id: $id}) RETURN count(u) AS users', ['id' => $user->id]))->toBe(['users' => 0])
        ->and(graphRow('MATCH (i:Itinerary) RETURN count(i) AS itineraries'))->toBe(['itineraries' => 0])
        ->and(graphRow('MATCH (f:User {id: $id}) RETURN count(f) AS friends', ['id' => $friend->id]))->toBe(['friends' => 1]);
});

it('does not sync before the commit, and drops the sync when the transaction rolls back', function () {
    try {
        DB::transaction(function () {
            User::factory()->create(['name' => 'Ghost']);

            expect(graphRow('MATCH (u:User) RETURN count(u) AS users'))->toBe(['users' => 0]);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(graphRow('MATCH (u:User) RETURN count(u) AS users'))->toBe(['users' => 0]);
});

it('keeps the Postgres write and logs a warning when the graph is down', function () {
    Log::spy();
    config(['neo4j.uri' => 'bolt://127.0.0.1:1']);
    app()->forgetInstance(ClientInterface::class);
    app()->forgetInstance(Graph::class);
    app()->forgetInstance(SyncUserNodeAction::class);

    $user = User::factory()->create(['name' => 'Offline']);

    expect(User::query()->find($user->id))->not->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn(string $event, array $context) => $event === 'graph.user_sync_failed' && $context['user_id'] === $user->id);
});

it('reconciles the graph users with Postgres (ducktail:sync-users)', function () {
    $ana = User::factory()->create(['name' => 'Ana']);
    graph()->write('MATCH (u:User {id: $id}) DETACH DELETE u', ['id' => $ana->id]);
    graph()->write('CREATE (:User {id: 999999, name: "Gone"})-[:CREATED]->(:Itinerary {id: "orphan", title: "x", isPublic: true})');

    $this->artisan('ducktail:sync-users')->expectsOutputToContain('1 usuários sincronizados no grafo; 1 nós órfãos removidos.')->assertSuccessful();

    expect(graph()->read('MATCH (u:User) RETURN u.id AS id, u.name AS name'))->toBe([['id' => $ana->id, 'name' => 'Ana']])
        ->and(graphRow('MATCH (i:Itinerary) RETURN count(i) AS total'))->toBe(['total' => 0]);
});
