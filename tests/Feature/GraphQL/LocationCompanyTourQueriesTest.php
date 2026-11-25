<?php

use App\Models\User;
use Database\Seeders\GraphSeeder;

/*
| Localidades (árvore país → estado → cidade), empresas e passeios, contra o grafo de exemplo do `GraphSeeder`.
*/

beforeEach(fn() => $this->seed(GraphSeeder::class));

describe('location(id)', function () {
    it('returns a city with its chain of locations, places (paged), companies and tours', function () {
        $location = graphql('{ location(id: "' . seed_id('seed-ouro-preto') . '") {
            id name kind latitude
            parent { id }
            ancestors { id kind }
            children { id }
            placeCount
            places(first: 2) { edges { node { id } } pageInfo { hasNextPage } }
            companies { id }
            tours { id }
        } }')->assertOk()->json('data.location');

        expect($location['name'])->toBe('Ouro Preto')
            ->and($location['kind'])->toBe('city')
            ->and($location['latitude'])->toBe(-20.3856)
            ->and($location['parent'])->toBe(['id' => seed_id('seed-minas-gerais')])
            ->and($location['ancestors'])->toBe([['id' => seed_id('seed-brasil'), 'kind' => 'country'], ['id' => seed_id('seed-minas-gerais'), 'kind' => 'state']])
            ->and($location['children'])->toBe([])
            ->and($location['placeCount'])->toBe(3)
            ->and(array_column(array_column($location['places']['edges'], 'node'), 'id'))->toBe([seed_id('seed-igreja-sao-francisco-ouro-preto'), seed_id('seed-matriz-pilar-ouro-preto')])
            ->and($location['places']['pageInfo']['hasNextPage'])->toBeTrue()
            ->and(array_column($location['companies'], 'id'))->toBe([seed_id('seed-expresso-estrada-real'), seed_id('seed-guia-barroco'), seed_id('seed-trilhas-do-ouro')])
            ->and(array_column($location['tours'], 'id'))->toBe([seed_id('seed-tour-aleijadinho'), seed_id('seed-tour-centro-ouro-preto'), seed_id('seed-tour-ouro-preto-mariana')]);
    });

    it('counts and lists the places of every location below, children with more places first', function () {
        $state = graphql('{ location(id: "' . seed_id('seed-minas-gerais') . '") { placeCount children { id } ancestors { id } places(first: 50) { edges { node { id } } } } }')
            ->json('data.location');

        expect($state['placeCount'])->toBe(12)
            ->and($state['places']['edges'])->toHaveCount(12)
            ->and(array_column($state['children'], 'id'))->toBe([
                seed_id('seed-belo-horizonte'), seed_id('seed-ouro-preto'), seed_id('seed-mariana'), seed_id('seed-sao-joao-del-rei'), seed_id('seed-congonhas'), seed_id('seed-tiradentes'),
            ])
            ->and($state['ancestors'])->toBe([['id' => seed_id('seed-brasil')]]);
    });

    it('searches the places of a location by name, exact name first, and by category', function () {
        $id = seed_id('seed-minas-gerais');
        $names = fn(string $args) => array_column(array_column(graphql('{ location(id: "' . $id . '") { places(' . $args . ') { edges { node { id } } } } }')->json('data.location.places.edges'), 'node'), 'id');

        $categories = graphql('{ location(id: "' . seed_id('seed-ouro-preto') . '") { categories { id name } } }')->json('data.location.categories');
        $church = collect($categories)->firstWhere('name', 'igreja')['id'];

        expect(array_column($categories, 'name'))->toBe(['igreja', 'museu'])
            ->and($names('term: "sao francisco"'))->toEqualCanonicalizing([
                seed_id('seed-igreja-sao-francisco-ouro-preto'), seed_id('seed-igreja-sao-francisco-sjdr'), seed_id('seed-igreja-pampulha'),
            ])
            ->and($names('term: "Igreja de São Francisco de Assis"')[0])->toBe(seed_id('seed-igreja-sao-francisco-ouro-preto'))
            ->and($names('term: "cristo"'))->toBe([])
            ->and($names('term: "   "'))->toHaveCount(12)
            ->and($names('categoryId: "' . $church . '", first: 50'))->toHaveCount(7)
            ->and($names('term: "matriz", categoryId: "' . $church . '"'))->toEqualCanonicalizing([seed_id('seed-matriz-pilar-ouro-preto'), seed_id('seed-matriz-santo-antonio-tiradentes')]);

        graphql('query ($t: String) { location(id: "' . $id . '") { places(term: $t) { edges { cursor } } } }', ['t' => str_repeat('a', 101)])
            ->assertJsonPath('errors.0.extensions.key', 'messages.places.invalid_search');
    });

    it('returns null for an unknown location', function () {
        graphql('{ location(id: "nope") { id } }')->assertExactJson(['data' => ['location' => null]]);
    });
});

describe('locations', function () {
    it('lists the roots without a parent and the children of a parent, by number of places', function () {
        expect(graphql('{ locations { id placeCount } }')->json('data.locations'))->toBe([
            ['id' => seed_id('seed-brasil'), 'placeCount' => 15],
            ['id' => seed_id('seed-portugal'), 'placeCount' => 3],
        ])->and(array_column(graphql('{ locations(parentId: "' . seed_id('seed-brasil') . '") { id } }')->json('data.locations'), 'id'))
            ->toBe([seed_id('seed-minas-gerais'), seed_id('seed-estado-rio-de-janeiro')]);
    });

    it('lists every location of a kind, at any level, and limits', function () {
        expect(graphql('{ locations(kind: "city", limit: 3) { id placeCount } }')->json('data.locations'))->toBe([
            ['id' => seed_id('seed-belo-horizonte'), 'placeCount' => 3],
            ['id' => seed_id('seed-lisboa'), 'placeCount' => 3],
            ['id' => seed_id('seed-ouro-preto'), 'placeCount' => 3],
        ]);

        graphql('{ locations(kind: "planet") { id } }')->assertJsonPath('errors.0.extensions.key', 'messages.locations.invalid_kind');
        graphql('{ locations(limit: 0) { id } }')->assertJsonPath('errors.0.extensions.key', 'messages.places.invalid_limit');
    });

    it('filters children by kind', function () {
        expect(array_column(graphql('{ locations(parentId: "' . seed_id('seed-minas-gerais') . '", kind: "city", limit: 2) { id } }')->json('data.locations'), 'id'))
            ->toBe([seed_id('seed-belo-horizonte'), seed_id('seed-ouro-preto')]);
    });
});

describe('company(id)', function () {
    it('returns the company with its base location, places and tours', function () {
        graphql('{ company(id: "' . seed_id('seed-pousada-caminho-real') . '") { id name kind website phone description baseLocation { id } places { id } tours { id } } }')
            ->assertOk()
            ->assertJsonPath('data.company.kind', 'lodging')
            ->assertJsonPath('data.company.baseLocation.id', seed_id('seed-tiradentes'))
            ->assertJsonPath('data.company.places', [['id' => seed_id('seed-matriz-santo-antonio-tiradentes')]])
            ->assertJsonPath('data.company.tours', []);
    });

    it('lists as places where it operates and where its tours stop', function () {
        $company = graphql('{ company(id: "' . seed_id('seed-carioca-a-pe') . '") { places { id } tours { name } } }')->json('data.company');

        expect(array_column($company['places'], 'id'))->toBe([seed_id('seed-cristo-redentor'), seed_id('seed-escadaria-selaron'), seed_id('seed-pao-de-acucar')])
            ->and(array_column($company['tours'], 'name'))->toBe(['Pão de Açúcar ao pôr do sol', 'Rio clássico']);
    });

    it('returns null for an unknown company', function () {
        graphql('{ company(id: "nope") { id } }')->assertExactJson(['data' => ['company' => null]]);
    });
});

describe('tour(id)', function () {
    it('returns the tour with its company and stops in order', function () {
        graphql('{ tour(id: "' . seed_id('seed-tour-belem') . '") { id name description durationMinutes price currency company { id } stops { order place { id location { name } } } } }')
            ->assertOk()
            ->assertExactJson(['data' => ['tour' => [
                'id' => seed_id('seed-tour-belem'),
                'name' => 'Belém numa manhã',
                'description' => 'O mosteiro, o padrão e a torre, a pé pela margem do rio.',
                'durationMinutes' => 210,
                'price' => 45,
                'currency' => 'EUR',
                'company' => ['id' => seed_id('seed-lisboa-ribeirinha')],
                'stops' => [
                    ['order' => 1, 'place' => ['id' => seed_id('seed-mosteiro-jeronimos'), 'location' => ['name' => 'Lisboa']]],
                    ['order' => 2, 'place' => ['id' => seed_id('seed-padrao-descobrimentos'), 'location' => ['name' => 'Lisboa']]],
                    ['order' => 3, 'place' => ['id' => seed_id('seed-torre-de-belem'), 'location' => ['name' => 'Lisboa']]],
                ],
            ]]]);
    });

    it('returns null for an unknown tour', function () {
        graphql('{ tour(id: "nope") { id } }')->assertExactJson(['data' => ['tour' => null]]);
    });
});

describe('toursThrough', function () {
    it('puts the tours through every place first, then the ones covering more', function () {
        $matches = graphql('query ($ids: [ID!]!) { toursThrough(placeIds: $ids) { coversAll matchedPlaceIds tour { id } } }', [
            'ids' => [seed_id('seed-igreja-sao-francisco-ouro-preto'), seed_id('seed-museu-inconfidencia'), seed_id('seed-mina-passagem'), 'nope'],
        ])->assertOk()->json('data.toursThrough');

        expect(array_column(array_column($matches, 'tour'), 'id'))->toBe([seed_id('seed-tour-ouro-preto-mariana'), seed_id('seed-tour-centro-ouro-preto'), seed_id('seed-tour-aleijadinho')])
            ->and(array_column($matches, 'coversAll'))->toBe([false, false, false])
            ->and($matches[0]['matchedPlaceIds'])->toHaveCount(3);

        $exact = graphql('{ toursThrough(placeIds: ["' . seed_id('seed-estacao-maria-fumaca') . '", "' . seed_id('seed-matriz-santo-antonio-tiradentes') . '", "' . seed_id('seed-estacao-maria-fumaca') . '"]) { coversAll tour { id } } }')
            ->json('data.toursThrough');

        expect($exact)->toBe([
            ['coversAll' => true, 'tour' => ['id' => seed_id('seed-tour-campo-das-vertentes')]],
            ['coversAll' => true, 'tour' => ['id' => seed_id('seed-tour-maria-fumaca')]],
        ]);
    });

    it('validates the number of places and the limit', function (string $args, string $key) {
        graphql("{ toursThrough({$args}) { coversAll } }")->assertJsonPath('errors.0.extensions.key', $key);
    })->with([
        ['placeIds: []', 'messages.tours.invalid_places'],
        ['placeIds: ["  "]', 'messages.tours.invalid_places'],
        ['placeIds: ["a","b","c","d","e","f","g","h","i","j","k"]', 'messages.tours.invalid_places'],
        ['placeIds: ["a"], limit: 51', 'messages.places.invalid_limit'],
    ]);
});

describe('tourSuggestions', function () {
    it('suggests tour stops not chosen yet: sharing tours with the chosen ones, then with a photo, then best rated', function () {
        $ana = accessToken(User::factory()->create());
        graphql('mutation { markVisited(placeId: "' . seed_id('seed-torre-de-belem') . '", rating: 5) { id } }', token: $ana);
        graph()->write('MATCH (p:Place {id: $id}) SET p.image = "https://example.org/x.jpg"', ['id' => seed_id('seed-pao-de-acucar')]);

        $ids = array_column(graphql('query ($ids: [ID!]) { tourSuggestions(placeIds: $ids, limit: 4) { id } }', [
            'ids' => [seed_id('seed-estacao-maria-fumaca')],
        ])->assertOk()->json('data.tourSuggestions'), 'id');

        expect($ids)->toBe([seed_id('seed-matriz-santo-antonio-tiradentes'), seed_id('seed-igreja-sao-francisco-sjdr'), seed_id('seed-pao-de-acucar'), seed_id('seed-torre-de-belem')])
            ->and($ids)->not->toContain(seed_id('seed-estacao-maria-fumaca'))
            ->and(graphql('{ tourSuggestions { id } }')->json('data.tourSuggestions'))->toHaveCount(8);
    });

    it('validates the places and the limit', function (string $args, string $key) {
        graphql("{ tourSuggestions({$args}) { id } }")->assertJsonPath('errors.0.extensions.key', $key);
    })->with([
        ['placeIds: ["a","b","c","d","e","f","g","h","i","j","k"]', 'messages.tours.invalid_places'],
        ['limit: 0', 'messages.places.invalid_limit'],
    ]);
});

describe('featuredPlaces', function () {
    it('lists places with a photo, the ones with more tours first, optionally inside a location', function () {
        graph()->write('MATCH (p:Place) WHERE p.id IN ["' . seed_id('seed-pao-de-acucar') . '", "' . seed_id('seed-torre-de-belem') . '", "' . seed_id('seed-mina-passagem') . '"] SET p.image = "https://example.org/" + p.id + ".jpg"');

        expect(array_column(graphql('{ featuredPlaces { id } }')->json('data.featuredPlaces'), 'id'))
            ->toBe([seed_id('seed-pao-de-acucar'), seed_id('seed-torre-de-belem'), seed_id('seed-mina-passagem')])
            ->and(array_column(graphql('{ featuredPlaces(locationId: "' . seed_id('seed-brasil') . '", limit: 1) { id } }')->json('data.featuredPlaces'), 'id'))
            ->toBe([seed_id('seed-pao-de-acucar')]);

        graphql('{ featuredPlaces(limit: 99) { id } }')->assertJsonPath('errors.0.extensions.key', 'messages.places.invalid_limit');
    });

    it('puts the best rated first and the unrated last, before how sought they are', function () {
        graph()->write('MATCH (p:Place) WHERE p.id IN ["' . seed_id('seed-pao-de-acucar') . '", "' . seed_id('seed-torre-de-belem') . '", "' . seed_id('seed-mina-passagem') . '"] SET p.image = "https://example.org/" + p.id + ".jpg"');
        graph()->write(
            'CREATE (a:User {id: 901, name: "a"}), (b:User {id: 902, name: "b"})
             WITH a, b MATCH (m:Place {id: $mina}), (t:Place {id: $torre})
             CREATE (a)-[:VISITED {rating: 5}]->(m), (b)-[:VISITED {rating: 4}]->(m), (a)-[:VISITED {rating: 3}]->(t)',
            ['mina' => seed_id('seed-mina-passagem'), 'torre' => seed_id('seed-torre-de-belem')]
        );

        expect(array_column(graphql('{ featuredPlaces { id rating } }')->json('data.featuredPlaces'), 'rating', 'id'))
            ->toBe([seed_id('seed-mina-passagem') => 4.5, seed_id('seed-torre-de-belem') => 3, seed_id('seed-pao-de-acucar') => null]);
    });
});

it('lists the places of a theme', function () {
    $themes = graphql('{ place(id: "' . seed_id('seed-igreja-pampulha') . '") { themes { name description places { id } } } }')->json('data.place.themes');

    expect($themes)->toBe([[
        'name' => 'Modernismo',
        'description' => 'A arquitetura moderna brasileira, de Niemeyer a Burle Marx.',
        'places' => [['id' => seed_id('seed-igreja-pampulha')], ['id' => seed_id('seed-praca-liberdade-bh')]],
    ]]);
});
