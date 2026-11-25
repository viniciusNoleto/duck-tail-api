<?php

/*
| Convenções do AGENTS.md que antes dependiam só de disciplina: aqui a suíte quebra se forem violadas.
| As checagens que leem o código-fonte com expressão regular são **heurísticas** (pegam o esquecimento
| comum, não provam todos os caminhos); o comportamento é provado nos testes de Feature.
*/

use App\Base\Graph\Graph;
use App\Base\GraphQL\ResolverProvider;
use Symfony\Component\Finder\Finder;

arch('actions are final, single-purpose and HTTP-free')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal()
    ->toHaveSuffix('Action')
    ->toHaveMethod('handle')
    ->not->toUse(['Illuminate\Http\Request', 'Illuminate\Http\JsonResponse', 'App\Base\Traits\Response', 'Nuwave\Lighthouse']);

arch('queries are final, read-only by name and HTTP-free')
    ->expect('App\Queries')
    ->classes()
    ->toBeFinal()
    ->toHaveSuffix('Query')
    ->toHaveMethod('get')
    ->not->toUse(['Illuminate\Http\Request', 'Nuwave\Lighthouse']);

arch('graphql resolvers are final, named after the field and live in the resolver namespace')
    ->expect(ResolverProvider::NAMESPACE)
    ->classes()
    ->toBeFinal()
    ->toHaveSuffix('Resolver')
    ->toHaveMethod('__invoke');

arch('resolvers stay thin: no Cypher and no driver, only Queries, Actions and DTOs')
    ->expect(ResolverProvider::NAMESPACE)
    ->not->toUse([Graph::class, 'Laudis\Neo4j', 'Illuminate\Support\Facades\DB']);

arch('the Neo4j driver is used only through App\Base\Graph\Graph')
    ->expect('Laudis\Neo4j')
    ->toOnlyBeUsedIn(['App\Base\Graph', 'App\Providers\AppServiceProvider']);

arch('there are no repositories: Actions and Queries are the data layer')
    ->expect('App')
    ->classes()
    ->not->toHaveSuffix('Repository');

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('env() only inside config')
    ->expect('env')
    ->not->toBeUsedIn('App');

it('retries every action transaction on deadlock (attempts from config)', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(app_path('Actions'))->name('*.php') as $file) {
        $source = $file->getContents();

        if (substr_count($source, 'DB::transaction(') !== substr_count($source, "attempts: config('database.transaction_attempts')")) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps graph writes out of the Query classes', function () {
    // Heurística: palavra-chave de escrita do Cypher numa Query (as Queries só leem; escrita é Action).
    $offenders = [];

    foreach (Finder::create()->files()->in(app_path('Queries'))->name('*.php') as $file) {
        if (preg_match('/\b(CREATE|MERGE|DELETE|DETACH|SET|REMOVE)\b/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('never interpolates request data into Cypher (values go as parameters)', function () {
    // Heurística: `{$dto->…}`, `{$args[…]}` ou `$args[…]` concatenado dentro de uma string com Cypher.
    // O que pode ser interpolado: projeções de `Cypher::…`, constantes e valores de enum.
    $offenders = [];

    foreach (Finder::create()->files()->in([app_path('Queries/Graph'), app_path('Actions/Graph')])->name('*.php') as $file) {
        if (preg_match('/\{\$(dto|args|input|term|query)\b|\'\s*\.\s*\$(dto|args)\b/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
