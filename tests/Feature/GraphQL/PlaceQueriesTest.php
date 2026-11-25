<?php

use App\Models\User;
use Database\Seeders\GraphSeeder;

/*
| Consultas públicas sobre lugares, contra o grafo de exemplo do `GraphSeeder`.
*/

beforeEach(fn() => $this->seed(GraphSeeder::class));

describe('place(id)', function () {
    it('returns the place with location, categories, themes, companies and tours in the contract shape', function () {
        $place = graphql('{ place(id: "' . seed_id('seed-igreja-sao-francisco-ouro-preto') . '") {
            id name description latitude longitude image
            location { id name kind ancestors { name } }
            categories { name }
            themes { name }
            companies { id name kind website baseLocation { name } }
            tours { id }
        } }')->assertOk()->json('data.place');

        expect(array_keys($place))->toBe(['id', 'name', 'description', 'latitude', 'longitude', 'image', 'location', 'categories', 'themes', 'companies', 'tours'])
            ->and($place['name'])->toBe('Igreja de São Francisco de Assis')
            ->and($place['latitude'])->toBe(-20.3866)
            ->and($place['location'])->toBe(['id' => seed_id('seed-ouro-preto'), 'name' => 'Ouro Preto', 'kind' => 'city', 'ancestors' => [['name' => 'Brasil'], ['name' => 'Minas Gerais']]])
            ->and(array_column($place['tours'], 'id'))->toBe([seed_id('seed-tour-aleijadinho'), seed_id('seed-tour-centro-ouro-preto'), seed_id('seed-tour-ouro-preto-mariana')])
            ->and($place['categories'])->toBe([['name' => 'igreja']])
            ->and(array_column($place['themes'], 'name'))->toBe(['Arte Barroca', 'Ciclo do Ouro'])
            ->and(array_column($place['companies'], 'kind'))->toBe(['guide', 'agency'])
            ->and($place['companies'][1]['baseLocation']['name'])->toBe('Ouro Preto');
    });

    it('returns null for an unknown id, without an error', function () {
        graphql('{ place(id: "nope") { id } }')->assertOk()->assertExactJson(['data' => ['place' => null]]);
    });

    it('explains every related place: same author, curated, same theme', function () {
        $related = collect(graphql('{ place(id: "' . seed_id('seed-igreja-sao-francisco-ouro-preto') . '") { related(limit: 20) { reason place { id } } } }')
            ->json('data.place.related'))->pluck('reason', 'place.id');

        expect($related[seed_id('seed-santuario-bom-jesus-congonhas')])->toBe('Obras-primas de Aleijadinho no ciclo do ouro')
            ->and($related[seed_id('seed-igreja-sao-francisco-sjdr')])->toBe('Mesmo autor: Aleijadinho')
            ->and($related[seed_id('seed-mina-passagem')])->toBe('Mesmo tema: Ciclo do Ouro')
            ->and($related)->not->toHaveKey(seed_id('seed-igreja-sao-francisco-ouro-preto'));
    });

    it('orders related places: same location, shared category, best rating, with a photo', function () {
        graph()->write('MATCH (n) DETACH DELETE n');
        graphPlace('source', ['location' => 'here', 'categories' => ['igreja'], 'themes' => ['T']]);
        graphPlace('f-nothing', ['name' => 'a', 'location' => 'there', 'themes' => ['T']]);
        graphPlace('e-photo', ['name' => 'b', 'location' => 'there', 'themes' => ['T']]);
        graphPlace('d-rated-4', ['name' => 'c', 'location' => 'there', 'themes' => ['T']]);
        graphPlace('a-rated-5', ['name' => 'd', 'location' => 'there', 'themes' => ['T']]);
        graphPlace('c-category', ['name' => 'e', 'location' => 'there', 'categories' => ['igreja'], 'themes' => ['T']]);
        graphPlace('b-location', ['name' => 'f', 'location' => 'here', 'themes' => ['T']]);
        graph()->write(
            'MATCH (e:Place {id: "e-photo"}), (d:Place {id: "d-rated-4"}), (a:Place {id: "a-rated-5"})
             SET e.image = "https://example.org/e.jpg"
             CREATE (u:User {id: 901, name: "u"})-[:VISITED {rating: 4}]->(d), (u)-[:VISITED {rating: 5}]->(a)'
        );

        $related = graphql('{ place(id: "source") { related(limit: 5) { reason place { id } } } }')->json('data.place.related');

        expect(array_column(array_column($related, 'place'), 'id'))->toBe(['b-location', 'c-category', 'a-rated-5', 'd-rated-4', 'e-photo'])
            ->and(array_unique(array_column($related, 'reason')))->toBe(['Mesmo tema: T']);
    });

    it('limits related places and validates the limit', function () {
        expect(graphql('{ place(id: "' . seed_id('seed-igreja-sao-francisco-ouro-preto') . '") { related(limit: 2) { reason } } }')->json('data.place.related'))->toHaveCount(2);

        graphql('{ place(id: "' . seed_id('seed-igreja-sao-francisco-ouro-preto') . '") { related(limit: 0) { reason } } }')
            ->assertJsonPath('errors.0.extensions.code', 'VALIDATION')
            ->assertJsonPath('errors.0.extensions.messages.en-us', 'The limit must be between 1 and 50.');
    });

    it('lists nearby places by distance within the radius', function () {
        $nearby = graphql('{ place(id: "' . seed_id('seed-igreja-sao-francisco-ouro-preto') . '") { nearby(radiusKm: 1) { distanceM place { id } } } }')
            ->json('data.place.nearby');

        expect(array_column(array_column($nearby, 'place'), 'id'))->toBe([seed_id('seed-museu-inconfidencia'), seed_id('seed-matriz-pilar-ouro-preto')])
            ->and($nearby[0]['distanceM'])->toBeGreaterThan(100)->toBeLessThan(130);
    });

    it('rejects a radius above the field limit', function () {
        graphql('{ place(id: "' . seed_id('seed-igreja-sao-francisco-ouro-preto') . '") { nearby(radiusKm: 500) { distanceM } } }')
            ->assertJsonPath('errors.0.extensions.key', 'messages.places.invalid_radius')
            ->assertJsonPath('errors.0.extensions.messages.pt-br', 'O raio deve ser maior que zero e de no máximo 20 km.');
    });

    it('shows no friends without a session and the followed visitors with one', function () {
        graphql('{ place(id: "' . seed_id('seed-mina-passagem') . '") { friendsWhoVisited { rating } } }')->assertJsonPath('data.place.friendsWhoVisited', []);

        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        graph()->write(
            'MATCH (a:User {id: $a}), (b:User {id: $b}), (p:Place {id: "' . seed_id('seed-mina-passagem') . '"})
             CREATE (a)-[:FOLLOWS]->(b), (b)-[:VISITED {date: date("2026-11-01"), rating: 4}]->(p)',
            ['a' => $ana->id, 'b' => $bruno->id]
        );

        graphql('{ place(id: "' . seed_id('seed-mina-passagem') . '") { friendsWhoVisited { date rating user { id name } } } }', token: accessToken($ana))
            ->assertJsonPath('data.place.friendsWhoVisited', [['date' => '2026-11-01', 'rating' => 4, 'user' => ['id' => (string)$bruno->id, 'name' => 'Bruno']]]);
    });
});

describe('searchPlaces', function () {
    it('finds by name prefix ignoring accents, most relevant first', function () {
        $names = collect(graphql('{ searchPlaces(term: "sao fran") { edges { node { name } } } }')->json('data.searchPlaces.edges'))->pluck('node.name');

        expect($names)->toHaveCount(3)
            ->and($names->every(fn(string $name) => str_contains($name, 'São Francisco')))->toBeTrue();
    });

    it('finds by name prefix typed with accents (prefix terms skip the index analyzer)', function () {
        $accented = collect(graphql('{ searchPlaces(term: "São Fran") { edges { node { name } } } }')->json('data.searchPlaces.edges'))->pluck('node.name');
        $plain = collect(graphql('{ searchPlaces(term: "sao fran") { edges { node { name } } } }')->json('data.searchPlaces.edges'))->pluck('node.name');

        expect($accented)->toHaveCount(3)
            ->and($accented->all())->toBe($plain->all());
    });

    it('puts the exact name first, then names starting with the term', function () {
        $ids = fn(string $term) => collect(graphql('query ($t: String) { searchPlaces(term: $t) { edges { node { id } } } }', ['t' => $term])->json('data.searchPlaces.edges'))->pluck('node.id')->all();

        expect($ids('Igreja de São Francisco de Assis')[0])->toBe(seed_id('seed-igreja-sao-francisco-ouro-preto'))
            ->and($ids('igreja de sao francisco de assis')[0])->toBe(seed_id('seed-igreja-sao-francisco-ouro-preto'))
            ->and($ids('Pão de Açúcar')[0])->toBe(seed_id('seed-pao-de-acucar'));
    });

    it('filters by category and pages with an opaque cursor', function () {
        $first = graphql('{ searchPlaces(category: "IGREJA", first: 4) { edges { cursor node { id } } pageInfo { hasNextPage endCursor } } }')
            ->json('data.searchPlaces');
        $second = graphql('query ($after: String) { searchPlaces(category: "igreja", first: 4, after: $after) { edges { node { id } } pageInfo { hasNextPage endCursor } } }', ['after' => $first['pageInfo']['endCursor']])
            ->json('data.searchPlaces');

        $ids = [...array_column(array_column($first['edges'], 'node'), 'id'), ...array_column(array_column($second['edges'], 'node'), 'id')];

        expect($first['pageInfo']['hasNextPage'])->toBeTrue()
            ->and($first['edges'][3]['cursor'])->toBe($first['pageInfo']['endCursor'])
            ->and($second['pageInfo']['hasNextPage'])->toBeFalse()
            ->and($ids)->toHaveCount(7)
            ->and(array_unique($ids))->toHaveCount(7);
    });

    it('treats Lucene syntax in the term as text', function () {
        graphql('{ searchPlaces(term: "igreja (\"matriz\" AND *") { edges { node { id } } } }')->assertOk()->assertJsonMissingPath('errors');
    });

    it('rejects a page size above 50 and a cursor that did not come from the API', function (string $args, string $key) {
        graphql("{ searchPlaces({$args}) { edges { cursor } } }")->assertJsonPath('errors.0.extensions.key', $key);
    })->with([
        ['first: 51', 'messages.places.invalid_page_size'],
        ['after: "bm9wZQ=="', 'messages.places.invalid_cursor'],
    ]);
});

describe('nearbyPlaces', function () {
    it('lists places around a coordinate', function () {
        $nearby = graphql('{ nearbyPlaces(lat: -20.38, lng: -43.5, radiusKm: 2) { distanceM place { id } } }')->json('data.nearbyPlaces');

        expect(array_column(array_column($nearby, 'place'), 'id'))->toBe([seed_id('seed-museu-inconfidencia'), seed_id('seed-igreja-sao-francisco-ouro-preto'), seed_id('seed-matriz-pilar-ouro-preto')]);
    });

    it('validates the coordinates', function () {
        graphql('{ nearbyPlaces(lat: 120, lng: -43.5) { distanceM } }')
            ->assertJsonPath('errors.0.extensions.key', 'messages.places.invalid_coordinates')
            ->assertJsonPath('errors.0.extensions.status', 422);
    });
});

describe('suggestedItinerary', function () {
    it('orders the theme places around the starting point by distance', function () {
        $stops = graphql('{ suggestedItinerary(placeId: "' . seed_id('seed-museu-inconfidencia') . '", theme: "ciclo do ouro") { distanceM place { id } } }')
            ->json('data.suggestedItinerary');

        expect(array_column(array_column($stops, 'place'), 'id'))->toBe([
            seed_id('seed-museu-inconfidencia'), seed_id('seed-igreja-sao-francisco-ouro-preto'), seed_id('seed-matriz-pilar-ouro-preto'), seed_id('seed-mina-passagem'),
        ])->and($stops[0]['distanceM'])->toEqual(0);
    });

    it('answers NOT_FOUND for an unknown start and VALIDATION for a blank theme', function () {
        graphql('{ suggestedItinerary(placeId: "nope", theme: "x") { distanceM } }')->assertJsonPath('errors.0.extensions.code', 'NOT_FOUND');
        graphql('{ suggestedItinerary(placeId: "' . seed_id('seed-mina-passagem') . '", theme: "  ") { distanceM } }')->assertJsonPath('errors.0.extensions.key', 'messages.places.theme_required');
    });
});
