<?php

use App\Actions\Graph\Seed\AssignSeedIdsAction;
use App\Base\Graph\Graph;
use App\Base\Graph\GraphSchema;
use App\Base\Graph\GraphTransaction;
use App\Exceptions\ApiException;
use App\Queries\Wikidata\FetchTouristPlacesQuery;
use Database\Seeders\GraphSeeder;
use Database\Seeders\RioSaoPauloGraphSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

describe('ducktail:graph-migrate', function () {
    it('creates every constraint and index, and can run again', function () {
        $this->artisan('ducktail:graph-migrate')->assertSuccessful();
        $this->artisan('ducktail:graph-migrate')->assertSuccessful();

        $constraints = array_column(graph()->read('SHOW CONSTRAINTS YIELD name RETURN name'), 'name');
        $indexes = array_column(graph()->read('SHOW INDEXES YIELD name, state RETURN name, state'), 'state', 'name');

        expect($constraints)->toContain(...array_keys(GraphSchema::CONSTRAINTS));

        foreach (array_keys(GraphSchema::INDEXES) as $index) {
            expect($indexes[$index] ?? null)->toBe('ONLINE');
        }
    });
});

describe('AssignSeedIdsAction', function () {
    it('replaces the readable seed-… ids of an old sample graph with the opaque seed_id(), keeping relationships', function () {
        graph()->write('CREATE (:Tour {id: "seed-tour-x"})-[:STOPS_AT {order: 1}]->(:Place {id: "seed-place-x"}), (:Place {id: "Q1"})');

        $this->artisan('ducktail:graph-migrate')->assertSuccessful();

        expect(graphRow('MATCH (t:Tour)-[:STOPS_AT]->(p:Place) RETURN t.id AS tour, p.id AS place'))
            ->toBe(['tour' => seed_id('seed-tour-x'), 'place' => seed_id('seed-place-x')])
            ->and(seed_id('seed-tour-x'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
            ->and(graphRow('MATCH (p:Place {id: "Q1"}) RETURN count(p) AS n'))->toBe(['n' => 1])
            ->and(app(AssignSeedIdsAction::class)->handle())->toBe(0);
    });

    it('seeds no readable id', function () {
        $this->seed(GraphSeeder::class);

        expect(graphRow('MATCH (n) WHERE n.id STARTS WITH "seed-" RETURN count(n) AS n'))->toBe(['n' => 0]);
    });
});

describe('ducktail:import', function () {
    it('imports the Wikidata places of an area with their chain of locations, idempotently', function () {
        Http::fake(['*' => Http::response(wikidataFixture())]);

        $this->artisan('ducktail:import', ['--area' => 'br-mg'])->expectsOutputToContain('2 lugares gravados')->assertSuccessful();
        $this->artisan('ducktail:import', ['--area' => 'BR-MG'])->assertSuccessful();

        Http::assertSent(fn($request) => str_contains($request['query'], '"BR-MG"') && $request->hasHeader('User-Agent'));

        expect(graph()->read(
            'MATCH (p:Place)-[:LOCATED_IN]->(c:Location)-[:WITHIN]->(s:Location)-[:WITHIN]->(country:Location)
             OPTIONAL MATCH (p)-[:HAS_CATEGORY]->(k:Category)
             WITH p, c, s, country, k ORDER BY k.name
             WITH p, c, s, country, collect(k.name) AS categories ORDER BY p.id
             RETURN p.id AS id, p.name AS name, p.image AS image, p.authors AS authors, c.id AS city, c.kind AS kind,
                    s.name AS state, s.kind AS stateKind, country.name AS country, categories'
        ))->toBe([
            ['id' => 'Q10300735', 'name' => 'Igreja de São Francisco de Assis (São João del-Rei)', 'image' => null, 'authors' => ['Aleijadinho'], 'city' => 'Q1756894', 'kind' => 'city',
                'state' => 'Minas Gerais', 'stateKind' => 'state', 'country' => 'Brasil', 'categories' => ['igreja']],
            ['id' => 'Q2337984', 'name' => 'Igreja São Francisco de Assis', 'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Igreja.jpg', 'authors' => ['Aleijadinho', 'Mestre Ataíde'], 'city' => 'Q271238', 'kind' => 'city',
                'state' => 'Minas Gerais', 'stateKind' => 'state', 'country' => 'Brasil', 'categories' => ['atração turística', 'igreja', 'patrimônio histórico']],
        ])
            ->and(graphRow('MATCH (l:Location) RETURN count(l) AS total'))->toBe(['total' => 4])
            ->and(graph()->read('MATCH (k:Category) RETURN k.name AS name, k.nameEsMx AS es, k.nameEnUs AS en ORDER BY name'))->toBe([
                ['name' => 'atração turística', 'es' => 'atracción turística', 'en' => 'tourist attraction'],
                ['name' => 'igreja', 'es' => 'iglesia', 'en' => 'church'],
                ['name' => 'patrimônio histórico', 'es' => null, 'en' => 'heritage site'],
            ]);
    });

    it('moves a re-imported place out of categories it no longer has and drops categories left without places', function () {
        Http::fake(['*' => Http::response(wikidataFixture())]);
        graph()->write('MERGE (p:Place {wikidataId: "Q10300735", id: "Q10300735", name: "x"}) MERGE (k:Category {name: "património histórico", id: "00000000-0000-0000-0000-000000000009"}) MERGE (p)-[:HAS_CATEGORY]->(k)');

        $this->artisan('ducktail:import', ['--area' => 'BR-MG'])->assertSuccessful();

        expect(graph()->read('MATCH (:Place {id: "Q10300735"})-[:HAS_CATEGORY]->(k:Category) RETURN k.name AS name'))->toBe([['name' => 'igreja']])
            ->and(graphRow('MATCH (k:Category {name: "património histórico"}) RETURN count(k) AS total'))->toBe(['total' => 0]);
    });

    it('reads the category names of a Wikidata type in pt-br, es and en', function () {
        expect(FetchTouristPlacesQuery::categories(['Igreja~igreja~Iglesia~church', '~património histórico~~', '~~~english only', 'museu', 'igreja~~~']))->toBe([
            ['name' => 'igreja', 'name_es' => 'iglesia', 'name_en' => 'church'],
            ['name' => 'patrimônio histórico', 'name_es' => null, 'name_en' => null],
            ['name' => 'museu', 'name_es' => null, 'name_en' => null],
        ])
            ->and(FetchTouristPlacesQuery::brazilianSpelling('estrutura arquitetónica, académica, também'))->toBe('estrutura arquitetônica, acadêmica, também');
    });

    it('validates the options', function () {
        $this->artisan('ducktail:import', ['--area' => 'Minas'])->assertFailed();
    });
});

describe('ducktail:proximity and ducktail:relate', function () {
    it('links places within the radius and places with the same author or, in the same location, style', function () {
        graphPlace('a', ['lat' => -20.0, 'lng' => -43.0, 'location' => 'c1', 'authors' => ['Aleijadinho'], 'styles' => ['barroco']]);
        graphPlace('b', ['lat' => -20.001, 'lng' => -43.0, 'location' => 'c1', 'styles' => ['barroco']]);
        graphPlace('c', ['lat' => -21.0, 'lng' => -44.0, 'location' => 'c2', 'authors' => ['Aleijadinho'], 'styles' => ['barroco']]);
        graphPlace('d', ['lat' => -21.0, 'lng' => -44.5, 'location' => 'c3', 'styles' => ['barroco']]);

        $this->artisan('ducktail:proximity', ['--radius' => 500])->assertSuccessful();
        $this->artisan('ducktail:relate')->assertSuccessful();
        $this->artisan('ducktail:relate')->assertSuccessful();

        expect(graph()->read('MATCH (x:Place)-[n:NEAR]->(y:Place) RETURN x.id AS from, y.id AS to'))->toBe([['from' => 'a', 'to' => 'b']])
            ->and(graph()->read('MATCH (x:Place)-[r:RELATED_TO]->(y:Place) RETURN x.id AS from, y.id AS to, r.kind AS kind, r.value AS value ORDER BY from, to'))->toBe([
                ['from' => 'a', 'to' => 'b', 'kind' => 'style', 'value' => 'barroco'],
                ['from' => 'a', 'to' => 'c', 'kind' => 'author', 'value' => 'Aleijadinho'],
            ]);
    });

    it('keeps curated relationships when recalculating', function () {
        graphPlace('a', ['authors' => ['X']]);
        graphPlace('b', ['authors' => ['X']]);
        graph()->write('MATCH (a:Place {id: "a"}), (b:Place {id: "b"}) CREATE (a)-[:RELATED_TO {kind: "curated", value: "Escolha da curadoria"}]->(b)');

        $this->artisan('ducktail:relate')->assertSuccessful();

        expect(graph()->read('MATCH (:Place)-[r:RELATED_TO]->(:Place) RETURN r.kind AS kind, r.value AS value'))
            ->toBe([['kind' => 'curated', 'value' => 'Escolha da curadoria']]);
    });

    it('rejects a non-positive radius', function () {
        $this->artisan('ducktail:proximity', ['--radius' => 0])->assertFailed();
    });
});

describe('GraphSeeder', function () {
    it('seeds the sample graph idempotently', function () {
        $this->seed(GraphSeeder::class);
        $this->seed(GraphSeeder::class);

        expect(graphRow('MATCH (p:Place) RETURN count(p) AS total'))->toBe(['total' => count(GraphSeeder::PLACES)])
            ->and(graphRow('MATCH (c:Company) RETURN count(c) AS total'))->toBe(['total' => count(GraphSeeder::COMPANIES)])
            ->and(graphRow('MATCH (:Place)-[r:RELATED_TO {kind: "curated"}]->(:Place) RETURN count(r) AS total'))->toBe(['total' => count(GraphSeeder::CURATED)])
            ->and(graphRow('MATCH (t:Tour) RETURN count(t) AS total'))->toBe(['total' => count(GraphSeeder::TOURS)])
            ->and(graphRow('MATCH (:Tour)-[s:STOPS_AT]->(:Place) RETURN count(s) AS total'))->toBe(['total' => array_sum(array_map(fn(array $tour) => count($tour['stops']), GraphSeeder::TOURS))])
            ->and(graphRow('MATCH (p:Place) WHERE NOT (p)-[:LOCATED_IN]->(:Location) RETURN count(p) AS orphans'))->toBe(['orphans' => 0])
            ->and(graphRow('MATCH (l:Location) WHERE l.kind <> "country" AND NOT (l)-[:WITHIN]->(:Location) RETURN count(l) AS orphans'))->toBe(['orphans' => 0]);
    });
});

describe('RioSaoPauloGraphSeeder', function () {
    it('adds Rio and São Paulo on top of the sample graph idempotently, adopting the Wikidata ids of the shared nodes', function () {
        $this->seed(GraphSeeder::class);
        $this->seed(RioSaoPauloGraphSeeder::class);
        $this->seed(RioSaoPauloGraphSeeder::class);

        $shared = count(array_intersect(array_column(GraphSeeder::PLACES, 'id'), array_column(RioSaoPauloGraphSeeder::PLACES, 'id')));
        $tours = [...GraphSeeder::TOURS, ...RioSaoPauloGraphSeeder::TOURS];

        expect($shared)->toBe(3)
            ->and(graphRow('MATCH (p:Place) RETURN count(p) AS total'))->toBe(['total' => count(GraphSeeder::PLACES) + count(RioSaoPauloGraphSeeder::PLACES) - $shared])
            ->and(graphRow('MATCH (c:Company) RETURN count(c) AS total'))->toBe(['total' => count(GraphSeeder::COMPANIES) + count(RioSaoPauloGraphSeeder::COMPANIES)])
            ->and(graphRow('MATCH (t:Tour) RETURN count(t) AS total'))->toBe(['total' => count($tours)])
            ->and(graphRow('MATCH (:Tour)-[s:STOPS_AT]->(:Place) RETURN count(s) AS total'))->toBe(['total' => array_sum(array_map(fn(array $tour) => count($tour['stops']), $tours))])
            ->and(graphRow('MATCH (p:Place {id: "' . seed_id('seed-cristo-redentor') . '"}) RETURN p.wikidataId AS wikidataId'))->toBe(['wikidataId' => 'Q79961'])
            ->and(graphRow('MATCH (c:Location {id: "' . seed_id('seed-sao-paulo') . '"})-[:WITHIN]->(s:Location)-[:WITHIN]->(b:Location) RETURN s.id AS state, b.id AS country'))
            ->toBe(['state' => seed_id('seed-estado-sao-paulo'), 'country' => seed_id('seed-brasil')])
            ->and(graphRow('MATCH (p:Place) WHERE NOT (p)-[:LOCATED_IN]->(:Location) RETURN count(p) AS orphans'))->toBe(['orphans' => 0])
            ->and(graphRow('MATCH (l:Location) WHERE l.kind <> "country" AND NOT (l)-[:WITHIN]->(:Location) RETURN count(l) AS orphans'))->toBe(['orphans' => 0]);
    });

    it('reuses a place the importer already created instead of duplicating it', function () {
        graph()->write('CREATE (:Place {id: "Q82941", wikidataId: "Q82941", name: "Museu de Arte de São Paulo Assis Chateaubriand"})');

        $this->seed(RioSaoPauloGraphSeeder::class);

        expect(graphRow('MATCH (p:Place {wikidataId: "Q82941"}) RETURN count(p) AS total, p.id AS id, p.name AS name'))
            ->toBe(['total' => 1, 'id' => 'Q82941', 'name' => 'Museu de Arte de São Paulo Assis Chateaubriand'])
            ->and(graphRow('MATCH (:Tour {id: "' . seed_id('seed-tour-museus-sp') . '"})-[s:STOPS_AT {order: 1}]->(p:Place) RETURN p.id AS id'))->toBe(['id' => 'Q82941'])
            ->and(graphRow('MATCH (p:Place {id: "Q82941"})-[:LOCATED_IN]->(l:Location) RETURN l.id AS id'))->toBe(['id' => seed_id('seed-sao-paulo')]);
    });
});

describe('Graph::transaction', function () {
    it('rolls back every write when the callback throws, and the next transaction works', function () {
        expect(fn() => graph()->transaction(function (GraphTransaction $transaction) {
            $transaction->run('CREATE (:Place {id: "temp", name: "temp"})');

            throw new ApiException('messages.itineraries.invalid_place');
        }))->toThrow(ApiException::class);

        expect(graphRow('MATCH (p:Place {id: "temp"}) RETURN count(p) AS total'))->toBe(['total' => 0])
            ->and(graph()->write('CREATE (p:Place {id: "ok", name: "ok"}) RETURN p.id AS id'))->toBe([['id' => 'ok']]);
    });

    it('translates a uniqueness violation into a recognizable error', function () {
        graphPlace('dup');

        try {
            graph()->write('CREATE (:Place {id: "dup", name: "again"})');
            $this->fail('expected a constraint violation');
        } catch (Throwable $exception) {
            expect(is_graph_constraint_violation($exception))->toBeTrue()
                ->and(Graph::isTransient($exception))->toBeFalse();
        }
    });
});

it('schedules the daily reconciliation of graph users', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn($event) => str_contains($event->command, 'ducktail:sync-users'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 4 * * *');
});

/**
 * Resposta do SPARQL (formato `application/sparql-results+json`) com duas linhas do mesmo lugar (como o
 * GROUP BY devolve com duas imagens), uma sem coordenada, uma sem nome e uma sem subdivisão.
 *
 * @return array<string, mixed>
 */
function wikidataFixture(): array {
    $row = fn(array $values) => array_map(fn(string $value) => ['type' => 'literal', 'value' => $value], $values);
    $state = [
        'state' => 'http://www.wikidata.org/entity/Q39109', 'stateLabel' => 'Minas Gerais',
        'country' => 'http://www.wikidata.org/entity/Q155', 'countryLabel' => 'Brasil',
    ];

    return ['results' => ['bindings' => [
        $row(['place' => 'http://www.wikidata.org/entity/Q2337984', 'placeLabel' => 'Igreja São Francisco de Assis', 'placeDescription' => 'igreja em Ouro Preto',
            'coord' => 'Point(-43.5034 -20.3866)', 'image' => 'http://commons.wikimedia.org/wiki/Special:FilePath/Igreja.jpg',
            'city' => 'http://www.wikidata.org/entity/Q271238', 'cityLabel' => 'Ouro Preto', 'cityCoord' => 'Point(-43.5035 -20.3856)',
            ...$state,
            'types' => 'igreja~igreja~iglesia~church|~atração turística~atracción turística~tourist attraction|~património histórico~~heritage site|~~~only english', 'authors' => 'Aleijadinho|Mestre Ataíde', 'styles' => 'barroco']),
        $row(['place' => 'http://www.wikidata.org/entity/Q2337984', 'placeLabel' => 'Igreja São Francisco de Assis', 'coord' => 'Point(-43.5034 -20.3866)',
            'image' => 'http://commons.wikimedia.org/wiki/Special:FilePath/Other.jpg', 'city' => 'http://www.wikidata.org/entity/Q271238', 'cityLabel' => 'Ouro Preto', ...$state]),
        $row(['place' => 'http://www.wikidata.org/entity/Q10300735', 'placeLabel' => 'Igreja de São Francisco de Assis (São João del-Rei)',
            'coord' => 'Point(-44.2617 -21.1365)', 'city' => 'http://www.wikidata.org/entity/Q1756894', 'cityLabel' => 'São João del-Rei', ...$state,
            'types' => 'igreja', 'authors' => 'Aleijadinho', 'styles' => '']),
        $row(['place' => 'http://www.wikidata.org/entity/Q1', 'placeLabel' => 'Sem coordenada', 'coord' => 'nada', 'city' => 'http://www.wikidata.org/entity/Q2', ...$state]),
        $row(['place' => 'http://www.wikidata.org/entity/Q3', 'placeLabel' => 'Q3', 'coord' => 'Point(-43 -20)', 'city' => 'http://www.wikidata.org/entity/Q2', ...$state]),
        $row(['place' => 'http://www.wikidata.org/entity/Q4', 'placeLabel' => 'Sem subdivisão', 'coord' => 'Point(-43 -20)', 'city' => 'http://www.wikidata.org/entity/Q2']),
    ]]];
}

it('builds the SPARQL for the requested area', function () {
    expect(FetchTouristPlacesQuery::sparql('pt-11', 10))->toContain('wdt:P300 "PT-11"', 'LIMIT 10', 'wd:Q570116', 'wdt:P1435', 'wdt:P17');
});
