<?php

use App\Actions\Auth\PruneExpiredTokensAction;
use App\Actions\Graph\Category\AssignCategoryIdsAction;
use App\Actions\Graph\Import\ImportPlacesAction;
use App\Actions\Graph\Proximity\ComputePlaceProximityAction;
use App\Actions\Graph\Relate\RelatePlacesAction;
use App\Actions\Graph\Schema\ApplyGraphSchemaAction;
use App\Actions\Graph\Seed\AssignSeedIdsAction;
use App\Actions\Graph\User\ReconcileUserNodesAction;
use App\Actions\User\SetUserPasswordAction;
use App\Models\User;
use App\Queries\Wikidata\FetchTouristPlacesQuery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('auth:prune-tokens', function (PruneExpiredTokensAction $action) {
    $deleted = $action->handle();

    $this->info("Tokens vencidos apagados: {$deleted['access_tokens']} access, {$deleted['refresh_tokens']} refresh.");
})->purpose('Apaga access e refresh tokens vencidos');

// Recuperação de acesso pelo operador do servidor (ex.: o admin esqueceu a senha e não há e-mail transacional).
Artisan::command('user:reset-password {email}', function (SetUserPasswordAction $action, string $email) {
    $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

    if (!$user) {
        $this->error('Usuário não encontrado.');

        return 1;
    }

    $password = (string)$this->secret('Nova senha (mínimo 8 caracteres)');

    if (mb_strlen($password) < 8 || $password !== $this->secret('Repita a nova senha')) {
        $this->error('Senha curta demais ou as duas não conferem. Nada foi alterado.');

        return 1;
    }

    $action->handle($user, $password);
    $this->info("Senha de {$user->email} redefinida; as sessões dele foram encerradas.");

    return 0;
})->purpose('Redefine a senha de um usuário (inclusive admin) e encerra as sessões dele');

// --- Grafo (Neo4j) ---------------------------------------------------------------------------------

// As "migrations" do grafo: constraints e índices (`App\Base\Graph\GraphSchema`). Idempotente; o deploy roda.
Artisan::command('ducktail:graph-migrate', function (ApplyGraphSchemaAction $action, AssignCategoryIdsAction $categories, AssignSeedIdsAction $seeds) {
    $statements = $action->handle();

    $this->info(count($statements) . ' constraints/índices garantidos no grafo (índices online).');
    $this->info($categories->handle() . ' categorias receberam id.');
    $this->info($seeds->handle() . ' nós de exemplo trocaram o id seed-… pelo UUID.');
})->purpose('Cria as constraints e os índices do Neo4j que ainda não existem e dá id opaco às categorias e aos nós de exemplo');

Artisan::command('ducktail:import {--area=BR-MG : subdivisão de país, código ISO 3166-2 (P300 no Wikidata)} {--limit=5000 : máximo de linhas do SPARQL}',
    function (FetchTouristPlacesQuery $query, ImportPlacesAction $action, AssignCategoryIdsAction $categories) {
        $area = strtoupper(trim((string)$this->option('area')));
        $limit = (int)$this->option('limit');

        if (!preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', $area) || $limit < 1) {
            $this->error('Use --area com um código ISO 3166-2 (ex.: BR-MG, PT-11) e --limit maior que zero.');

            return 1;
        }

        $this->info("Consultando o Wikidata ({$area})…");
        $places = $query->get($area, $limit);
        $this->info(count($places) . ' lugares recebidos; gravando no grafo…');
        $written = $action->handle($places);
        $categories->handle();
        $this->info("{$written} lugares gravados (MERGE por wikidataId). Depois rode ducktail:proximity e ducktail:relate.");

        return 0;
    })->purpose('Importa lugares turísticos de uma subdivisão de país do Wikidata (CC0) para o grafo');

Artisan::command('ducktail:proximity {--radius=3000 : raio entre lugares, em metros}',
    function (ComputePlaceProximityAction $places) {
        $radius = (float)$this->option('radius');

        if ($radius <= 0) {
            $this->error('O raio precisa ser maior que zero.');

            return 1;
        }

        $this->info($places->handle($radius) . " arestas NEAR entre lugares (até {$radius} m).");

        return 0;
    })->purpose('Recalcula as arestas NEAR entre lugares pela distância');

Artisan::command('ducktail:relate', function (RelatePlacesAction $action) {
    $this->info($action->handle() . ' arestas RELATED_TO (mesmo autor; mesmo estilo na mesma localidade).');
})->purpose('Recalcula as relações RELATED_TO automáticas entre lugares (autor/estilo em comum)');

Artisan::command('ducktail:sync-users', function (ReconcileUserNodesAction $action) {
    $result = $action->handle();

    $this->info("{$result['synced']} usuários sincronizados no grafo; {$result['removed']} nós órfãos removidos.");
})->purpose('Reconcilia os nós User do grafo com os usuários do Postgres');

// Rodado pelo serviço `scheduler` do docker-compose (`php artisan schedule:work`).
Schedule::command('auth:prune-tokens')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
Schedule::command('ducktail:sync-users')->dailyAt('04:00')->withoutOverlapping()->onOneServer();
