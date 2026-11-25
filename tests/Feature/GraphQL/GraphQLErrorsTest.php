<?php

use App\Base\Graph\Graph;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Contracts\ClientInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;

it('answers an unexpected failure with INTERNAL and the generic message, and logs it with the request id and user', function () {
    $user = User::factory()->create();
    $token = accessToken($user);
    $handler = new TestHandler;
    Log::getLogger()->pushHandler($handler);
    graphDown();

    graphql('{ place(id: "x") { id } }', token: $token)
        ->assertOk()
        ->assertJsonPath('errors.0.extensions.code', 'INTERNAL')
        ->assertJsonPath('errors.0.extensions.status', 500)
        ->assertJsonPath('errors.0.extensions.messages.en-us', 'Unexpected error. Please try again.')
        ->assertJsonMissingPath('errors.0.extensions.message_error')
        ->assertJsonMissingPath('errors.0.trace');

    $error = collect($handler->getRecords())->first(fn(LogRecord $record) => $record->level === Level::Error);

    expect($error)->not->toBeNull()
        ->and($error->extra)->toMatchArray(['user_id' => $user->id])
        ->and($error->extra)->toHaveKey('request_id');
});

it('keeps the GraphQL syntax errors as GraphQL reports them', function () {
    graphql('{ place(id: "x") { nope } }')
        ->assertOk()
        ->assertJsonPath('errors.0.message', fn(string $message) => str_starts_with($message, 'Cannot query field "nope" on type "Place".'))
        ->assertJsonMissingPath('errors.0.extensions.key');
});

it('limits GraphQL requests per client', function () {
    foreach (range(1, 240) as $_) {
        $this->postJson('/graphql', ['query' => '{ me { id } }']);
    }

    $this->postJson('/graphql', ['query' => '{ me { id } }'])->assertTooManyRequests()->assertJsonPath('success', false);
});

it('checks the graph in the health endpoint', function () {
    $this->get('/up')->assertOk();

    graphDown();

    $this->get('/up')->assertStatus(500);
});

/**
 * Neo4j fora do ar: o cliente passa a apontar para uma porta onde nada responde.
 */
function graphDown(): void {
    config(['neo4j.uri' => 'bolt://127.0.0.1:1']);
    app()->forgetInstance(ClientInterface::class);
    app()->forgetInstance(Graph::class);
}
