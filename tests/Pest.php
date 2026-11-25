<?php

use App\Actions\Auth\CreateAccessTokenAction;
use App\Actions\Graph\Schema\ApplyGraphSchemaAction;
use App\Base\Graph\Graph;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature: requisições reais contra o banco `${DB_DATABASE}_testing` (Postgres),
| recriado pelas migrations e isolado em transação a cada teste, e contra o
| Neo4j dos testes (`NEO4J_TESTING_URI`), esvaziado antes de cada teste.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // O grafo não tem transação por teste: começa vazio (só com constraints e índices) a cada teste.
    ->beforeEach(fn() => resetGraph())
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

// Concorrência: processos de verdade (pcntl_fork), cada um com a própria conexão. Os dados precisam
// estar commitados para os filhos enxergarem, então aqui é truncamento, não transação.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Access token válido (para `withToken`) do usuário informado ou de um novo.
 *
 * @param User|null $user
 * @return string
 */
function accessToken(?User $user = null): string {
    $user ??= User::factory()->create();

    return app(CreateAccessTokenAction::class)->handle($user->id)->token;
}

/**
 * Simula falha de banco (o endpoint deve responder 500 com a mensagem `…_error`): renomeia a
 * tabela dentro da transação do teste (o `RefreshDatabase` desfaz no rollback).
 *
 * @param string $table
 * @return void
 */
function failTable(string $table): void {
    DB::statement("ALTER TABLE {$table} RENAME TO {$table}_unavailable");
}

/**
 * Roda `$work($worker)` em `$workers` processos paralelos (fork), todos largando no mesmo instante, e
 * devolve o resultado de cada um: `['ok' => true, 'value' => …]` ou
 * `['ok' => false, 'error' => classe, 'key' => chave da ApiException|null, 'sqlstate' => …]`.
 * O pai fecha a conexão antes do fork: cada filho abre a sua (conexão PDO não pode ser compartilhada).
 * Filho que não termina em `$timeout_seconds` (lock preso, laço infinito) é morto com SIGKILL e volta
 * como `['ok' => false, 'error' => 'timeout']`: o teste falha em vez de pendurar a suíte e o CI.
 *
 * @param int $workers
 * @param Closure(int): mixed $work
 * @param int $timeout_seconds
 * @return array<int, array<string, mixed>>
 */
function runConcurrently(int $workers, Closure $work, int $timeout_seconds = 30): array {
    DB::disconnect();

    $dir = sys_get_temp_dir() . '/ducktail-concurrency-' . bin2hex(random_bytes(6));
    mkdir($dir);
    $start_at = microtime(true) + 0.5;
    $pids = [];

    foreach (range(0, $workers - 1) as $worker) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            try {
                time_sleep_until($start_at);
                $result = ['ok' => true, 'value' => $work($worker)];
            } catch (Throwable $exception) {
                $result = [
                    'ok' => false,
                    'error' => $exception::class,
                    'key' => $exception instanceof ApiException ? $exception->message_key : null,
                    'sqlstate' => (string)$exception->getCode(),
                    'message' => $exception->getMessage(),
                ];
            }

            file_put_contents("{$dir}/{$worker}", serialize($result));
            DB::disconnect();
            // Sai sem rodar shutdown do PHPUnit/Laravel do processo pai.
            posix_kill(getmypid(), SIGKILL);
        }

        $pids[$worker] = $pid;
    }

    $deadline = $start_at + $timeout_seconds;
    $timed_out = [];

    while ($pids !== []) {
        foreach ($pids as $worker => $pid) {
            if (pcntl_waitpid($pid, $status, WNOHANG) !== 0) {
                unset($pids[$worker]);
            }
        }

        if ($pids !== [] && microtime(true) > $deadline) {
            foreach ($pids as $worker => $pid) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
                $timed_out[] = $worker;
            }

            $pids = [];
        }

        usleep(10_000);
    }

    $results = [];

    foreach (range(0, $workers - 1) as $worker) {
        $results[$worker] = in_array($worker, $timed_out, true)
            ? ['ok' => false, 'error' => 'timeout']
            : (unserialize((string)@file_get_contents("{$dir}/{$worker}")) ?: ['ok' => false, 'error' => 'no result']);
        @unlink("{$dir}/{$worker}");
    }

    @rmdir($dir);

    return $results;
}

/**
 * Roda `$callback` com uma tentativa só por transação (`database.transaction_attempts = 1`): um deadlock
 * deixa de ser absorvido pelo retry e aparece no resultado do processo (`sqlstate` `40P01`). É assim que os
 * testes de concorrência provam "não há ciclo de locks", sem depender das estatísticas do Postgres.
 *
 * @template T
 * @param Closure(): T $callback
 * @return T
 */
function withoutTransactionRetries(Closure $callback): mixed {
    $attempts = config('database.transaction_attempts');
    config(['database.transaction_attempts' => 1]);

    try {
        return $callback();
    } finally {
        config(['database.transaction_attempts' => $attempts]);
    }
}

/**
 * Esvazia o grafo de teste e garante constraints/índices (uma vez por processo: `IF NOT EXISTS` é barato,
 * mas esperar os índices a cada teste não). `TestCase` garante que este é o Neo4j dos testes.
 *
 * @return void
 */
function resetGraph(): void {
    static $schema_applied = false;

    graph()->write('MATCH (n) DETACH DELETE n');

    if (!$schema_applied) {
        app(ApplyGraphSchemaAction::class)->handle();
        $schema_applied = true;
    }
}

/**
 * @return Graph
 */
function graph(): Graph {
    return app(Graph::class);
}

/**
 * Uma linha (a primeira) de uma consulta ao grafo de teste, para conferir o que foi gravado.
 *
 * @param string $cypher
 * @param array<string, mixed> $parameters
 * @return array<string, mixed>|null
 */
function graphRow(string $cypher, array $parameters = []): ?array {
    return graph()->read($cypher, $parameters)[0] ?? null;
}

/**
 * POST /graphql como o web faz (com o Bearer, se houver).
 *
 * @param string $query
 * @param array<string, mixed> $variables
 * @param string|null $token
 * @return TestResponse
 */
function graphql(string $query, array $variables = [], ?string $token = null): TestResponse {
    $request = $token ? test()->withToken($token) : test();

    return $request->postJson('/graphql', ['query' => $query, 'variables' => $variables === [] ? new stdClass : $variables]);
}

/**
 * Lugar mínimo no grafo de teste (localidade incluída), com as relações opcionais.
 *
 * @param string $id
 * @param array<string, mixed> $attributes name, lat, lng, location, categories, themes, authors, styles, description
 * @return void
 */
function graphPlace(string $id, array $attributes = []): void {
    $attributes += [
        'name' => $id, 'lat' => -20.0, 'lng' => -43.0, 'location' => 'location-1', 'description' => null,
        'categories' => [], 'themes' => [], 'authors' => [], 'styles' => [],
    ];

    graph()->write(
        'MERGE (l:Location {id: $location}) ON CREATE SET l.name = $location, l.kind = "city", l.location = point({latitude: $lat, longitude: $lng})
         CREATE (p:Place {id: $id, name: $name, description: $description, location: point({latitude: $lat, longitude: $lng}), authors: $authors, styles: $styles})
         CREATE (p)-[:LOCATED_IN]->(l)
         WITH p
         CALL (p) { UNWIND $categories AS name MERGE (k:Category {name: name}) ON CREATE SET k.id = randomUUID() MERGE (p)-[:HAS_CATEGORY]->(k) }
         CALL (p) { UNWIND $themes AS name MERGE (t:Theme {name: name}) MERGE (p)-[:PART_OF]->(t) }',
        ['id' => $id] + $attributes
    );
}
