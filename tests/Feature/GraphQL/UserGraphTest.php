<?php

use App\Models\User;
use Database\Seeders\GraphSeeder;

/*
| `me`, `recommendations` e as mutations (todas exigem sessão).
*/

beforeEach(function () {
    $this->seed(GraphSeeder::class);
    $this->ana = User::factory()->create(['name' => 'Ana']);
    $this->token = accessToken($this->ana);
});

describe('session', function () {
    it('answers me with null without a session', function () {
        graphql('{ me { id } }')->assertExactJson(['data' => ['me' => null]]);
    });

    it('requires a session for recommendations and every mutation', function (string $document) {
        graphql($document)
            ->assertOk()
            ->assertJsonPath('errors.0.extensions.code', 'UNAUTHENTICATED')
            ->assertJsonPath('errors.0.extensions.category', 'authentication')
            ->assertJsonPath('errors.0.extensions.messages.en-us', 'You need to sign in to continue.');
    })->with([
        '{ recommendations { id } }',
        'mutation { markVisited(placeId: "' . seed_id('seed-mina-passagem') . '") { id } }',
        'mutation { wantToVisit(placeId: "' . seed_id('seed-mina-passagem') . '") { id } }',
        'mutation { unwantToVisit(placeId: "' . seed_id('seed-mina-passagem') . '") { id } }',
        'mutation { removeVisitRating(placeId: "' . seed_id('seed-mina-passagem') . '") { id } }',
        'mutation { follow(userId: "1") { id } }',
        'mutation { createItinerary(input: {title: "x", stops: []}) { id } }',
    ]);

    it('answers an invalid or expired token with 401 in the REST envelope, so the web refreshes and retries', function () {
        $this->withToken('not-a-token')
            ->postJson('/graphql', ['query' => '{ me { id } }'])
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => api_message('messages.unauthorized'), 'data' => null]);
    });
});

describe('unwantToVisit', function () {
    it('drops the place from the wish list, idempotently', function () {
        $place = seed_id('seed-mina-passagem');
        graphql('mutation { wantToVisit(placeId: "' . $place . '") { id } }', token: $this->token)->assertOk();

        graphql('mutation { unwantToVisit(placeId: "' . $place . '") { id } }', token: $this->token)
            ->assertOk()
            ->assertJsonPath('data.unwantToVisit', ['id' => $place]);
        graphql('mutation { unwantToVisit(placeId: "' . $place . '") { id } }', token: $this->token)->assertOk();

        graphql('{ me { wantsToVisit { id } } }', token: $this->token)->assertJsonPath('data.me.wantsToVisit', []);
    });

    it('answers NOT_FOUND for an unknown place', function () {
        graphql('mutation { unwantToVisit(placeId: "nope") { id } }', token: $this->token)
            ->assertJsonPath('errors.0.extensions.code', 'NOT_FOUND');
    });
});

describe('Place.rating', function () {
    it('averages the ratings of the visits, ignoring visits without one', function () {
        $place = seed_id('seed-mina-passagem');
        $bruno = accessToken(User::factory()->create(['name' => 'Bruno']));
        $carla = accessToken(User::factory()->create(['name' => 'Carla']));

        graphql('{ place(id: "' . $place . '") { rating ratingCount } }')->assertJsonPath('data.place', ['rating' => null, 'ratingCount' => 0]);

        graphql('mutation { markVisited(placeId: "' . $place . '", rating: 5) { id } }', token: $this->token);
        graphql('mutation { markVisited(placeId: "' . $place . '", rating: 4) { id } }', token: $bruno);
        graphql('mutation { markVisited(placeId: "' . $place . '") { id } }', token: $carla);

        graphql('{ place(id: "' . $place . '") { rating ratingCount } }')->assertJsonPath('data.place', ['rating' => 4.5, 'ratingCount' => 2]);
    });
});

describe('Place.myVisit', function () {
    it('is null without a session and without a visit, and the session user\'s own visit otherwise', function () {
        $place = seed_id('seed-mina-passagem');
        $this->travelTo('2026-11-17 12:00:00');
        graphql('{ place(id: "' . $place . '") { myVisit { date } } }')->assertExactJson(['data' => ['place' => ['myVisit' => null]]]);

        $bruno = accessToken(User::factory()->create(['name' => 'Bruno']));
        graphql('mutation { markVisited(placeId: "' . $place . '", rating: 2) { id } }', token: $bruno)->assertOk();
        $this->token = accessToken($this->ana);
        graphql('{ place(id: "' . $place . '") { myVisit { date } } }', token: $this->token)->assertExactJson(['data' => ['place' => ['myVisit' => null]]]);

        graphql('mutation { markVisited(placeId: "' . $place . '", rating: 4) { id } }', token: $this->token)->assertOk();

        graphql('{ place(id: "' . $place . '") { myVisit { date rating user { id name } } } }', token: $this->token)
            ->assertExactJson(['data' => ['place' => ['myVisit' => ['date' => '2026-11-17', 'rating' => 4, 'user' => ['id' => (string)$this->ana->id, 'name' => 'Ana']]]]]);
    });
});

describe('removeVisitRating', function () {
    it('clears the rating and keeps the visit and its date, idempotently', function () {
        $place = seed_id('seed-mina-passagem');
        $this->travelTo('2026-11-17 12:00:00');
        graphql('mutation { markVisited(placeId: "' . $place . '", rating: 5) { id } }', token: $this->token)->assertOk();
        $this->travelTo('2026-11-20 12:00:00');
        $this->token = accessToken($this->ana);

        graphql('mutation { removeVisitRating(placeId: "' . $place . '") { id rating ratingCount } }', token: $this->token)
            ->assertOk()
            ->assertJsonPath('data.removeVisitRating', ['id' => $place, 'rating' => null, 'ratingCount' => 0]);
        graphql('mutation { removeVisitRating(placeId: "' . $place . '") { id } }', token: $this->token)->assertOk();

        graphql('{ place(id: "' . $place . '") { myVisit { date rating } } }', token: $this->token)
            ->assertJsonPath('data.place.myVisit', ['date' => '2026-11-17', 'rating' => null]);
    });

    it('does nothing for a place the user never visited and answers NOT_FOUND for an unknown place', function () {
        graphql('mutation { removeVisitRating(placeId: "' . seed_id('seed-mina-passagem') . '") { id } }', token: $this->token)
            ->assertJsonPath('data.removeVisitRating.id', seed_id('seed-mina-passagem'));

        expect(graphRow('MATCH (:User {id: $id})-[v:VISITED]->() RETURN count(v) AS total', ['id' => $this->ana->id]))->toBe(['total' => 0]);

        graphql('mutation { removeVisitRating(placeId: "nope") { id } }', token: $this->token)
            ->assertJsonPath('errors.0.extensions.code', 'NOT_FOUND');
    });
});

describe('markVisited', function () {
    it('records the visit with today and the rating, and drops the place from the wish list', function () {
        $this->travelTo('2026-11-17 12:00:00');
        graphql('mutation { wantToVisit(placeId: "' . seed_id('seed-mina-passagem') . '") { id } }', token: $this->token)->assertOk();

        graphql('mutation { markVisited(placeId: "' . seed_id('seed-mina-passagem') . '", rating: 5) { id name } }', token: $this->token)
            ->assertOk()
            ->assertJsonPath('data.markVisited', ['id' => seed_id('seed-mina-passagem'), 'name' => 'Mina da Passagem']);

        expect(graphRow('MATCH (:User {id: $id})-[v:VISITED]->(:Place {id: "' . seed_id('seed-mina-passagem') . '"}) RETURN toString(v.date) AS date, v.rating AS rating', ['id' => $this->ana->id]))
            ->toBe(['date' => '2026-11-17', 'rating' => 5]);

        graphql('{ me { id name visited { id } wantsToVisit { id } } }', token: $this->token)
            ->assertJsonPath('data.me', ['id' => (string)$this->ana->id, 'name' => 'Ana', 'visited' => [['id' => seed_id('seed-mina-passagem')]], 'wantsToVisit' => []]);
    });

    it('updates the same visit when marked again', function () {
        graphql('mutation { markVisited(placeId: "' . seed_id('seed-mina-passagem') . '", rating: 2) { id } }', token: $this->token);
        graphql('mutation { markVisited(placeId: "' . seed_id('seed-mina-passagem') . '", rating: 4) { id } }', token: $this->token);

        expect(graphRow('MATCH (:User {id: $id})-[v:VISITED]->() RETURN count(v) AS visits, max(v.rating) AS rating', ['id' => $this->ana->id]))
            ->toBe(['visits' => 1, 'rating' => 4]);
    });

    it('validates the rating and the place', function (string $args, string $key, int $status) {
        graphql("mutation { markVisited({$args}) { id } }", token: $this->token)
            ->assertJsonPath('errors.0.extensions.key', $key)
            ->assertJsonPath('errors.0.extensions.status', $status);
    })->with([
        ['placeId: "' . seed_id('seed-mina-passagem') . '", rating: 6', 'messages.visits.invalid_rating', 422],
        ['placeId: "' . seed_id('seed-mina-passagem') . '", rating: 0', 'messages.visits.invalid_rating', 422],
        ['placeId: "nope"', 'messages.places.not_found', 404],
    ]);

    it('recreates the user node when the observer sync was missed', function () {
        graph()->write('MATCH (u:User {id: $id}) DETACH DELETE u', ['id' => $this->ana->id]);

        graphql('mutation { markVisited(placeId: "' . seed_id('seed-mina-passagem') . '") { id } }', token: $this->token)->assertJsonMissingPath('errors');

        expect(graphRow('MATCH (u:User {id: $id}) RETURN u.name AS name', ['id' => $this->ana->id]))->toBe(['name' => 'Ana']);
    });
});

describe('wantToVisit', function () {
    it('is idempotent', function () {
        graphql('mutation { wantToVisit(placeId: "' . seed_id('seed-igreja-pampulha') . '") { id } }', token: $this->token)->assertJsonPath('data.wantToVisit.id', seed_id('seed-igreja-pampulha'));
        graphql('mutation { wantToVisit(placeId: "' . seed_id('seed-igreja-pampulha') . '") { id } }', token: $this->token);

        expect(graphRow('MATCH (:User {id: $id})-[w:WANTS_TO_VISIT]->() RETURN count(w) AS total', ['id' => $this->ana->id]))->toBe(['total' => 1]);
    });

    it('answers NOT_FOUND for an unknown place', function () {
        graphql('mutation { wantToVisit(placeId: "nope") { id } }', token: $this->token)->assertJsonPath('errors.0.extensions.code', 'NOT_FOUND');
    });
});

describe('follow', function () {
    it('follows another user, once', function () {
        $bruno = User::factory()->create(['name' => 'Bruno']);

        graphql('mutation ($id: ID!) { follow(userId: $id) { id name } }', ['id' => (string)$bruno->id], $this->token)
            ->assertJsonPath('data.follow', ['id' => (string)$bruno->id, 'name' => 'Bruno']);
        graphql('mutation ($id: ID!) { follow(userId: $id) { id } }', ['id' => (string)$bruno->id], $this->token);

        graphql('{ me { following { id name } } }', token: $this->token)
            ->assertJsonPath('data.me.following', [['id' => (string)$bruno->id, 'name' => 'Bruno']]);
    });

    it('refuses to follow yourself and unknown users', function (Closure $id, string $key) {
        graphql('mutation ($id: ID!) { follow(userId: $id) { id } }', ['id' => $id($this->ana)], $this->token)->assertJsonPath('errors.0.extensions.key', $key);
    })->with([
        'self' => [fn(User $ana) => (string)$ana->id, 'messages.follows.cannot_follow_self'],
        'unknown' => [fn() => '999999', 'messages.users.not_found'],
        'invalid' => [fn() => 'abc', 'messages.users.not_found'],
    ]);
});

describe('createItinerary', function () {
    $create = 'mutation ($input: ItineraryInput!) { createItinerary(input: $input) { id title isPublic stops { day order place { id } } } }';

    it('creates the itinerary with ordered stops', function () use ($create) {
        $itinerary = graphql($create, ['input' => ['title' => '  Barroco em dois dias ', 'stops' => [
            ['placeId' => seed_id('seed-santuario-bom-jesus-congonhas'), 'day' => 2, 'order' => 1],
            ['placeId' => seed_id('seed-igreja-sao-francisco-ouro-preto'), 'day' => 1, 'order' => 1],
        ]]], $this->token)->assertOk()->json('data.createItinerary');

        expect($itinerary['title'])->toBe('Barroco em dois dias')
            ->and($itinerary['isPublic'])->toBeFalse()
            ->and($itinerary['id'])->toMatch('/^[0-9a-f-]{36}$/')
            ->and($itinerary['stops'])->toBe([
                ['day' => 1, 'order' => 1, 'place' => ['id' => seed_id('seed-igreja-sao-francisco-ouro-preto')]],
                ['day' => 2, 'order' => 1, 'place' => ['id' => seed_id('seed-santuario-bom-jesus-congonhas')]],
            ]);
    });

    it('shows private itineraries only to their owner', function () use ($create) {
        graphql($create, ['input' => ['title' => 'Privado', 'stops' => [['placeId' => seed_id('seed-mina-passagem'), 'day' => 1, 'order' => 1]]]], $this->token);
        graphql($create, ['input' => ['title' => 'Público', 'isPublic' => true, 'stops' => [['placeId' => seed_id('seed-mina-passagem'), 'day' => 1, 'order' => 1]]]], $this->token);
        $bruno = User::factory()->create();
        graphql('mutation ($id: ID!) { follow(userId: $id) { id } }', ['id' => (string)$this->ana->id], accessToken($bruno));

        expect(collect(graphql('{ me { itineraries { title } } }', token: $this->token)->json('data.me.itineraries'))->pluck('title')->sort()->values()->all())->toBe(['Privado', 'Público'])
            ->and(graphql('{ me { following { itineraries { title } } } }', token: accessToken($bruno))->json('data.me.following.0.itineraries'))->toBe([['title' => 'Público']]);
    });

    it('validates the input', function (array $input, string $key) use ($create) {
        graphql($create, ['input' => $input], $this->token)
            ->assertJsonPath('errors.0.extensions.key', $key)
            ->assertJsonPath('errors.0.extensions.code', 'VALIDATION');
    })->with([
        'blank title' => [['title' => '   ', 'stops' => [['placeId' => seed_id('seed-mina-passagem'), 'day' => 1, 'order' => 1]]], 'messages.itineraries.title_required'],
        'long title' => [['title' => str_repeat('a', 151), 'stops' => [['placeId' => seed_id('seed-mina-passagem'), 'day' => 1, 'order' => 1]]], 'messages.itineraries.title_required'],
        'no stops' => [['title' => 'x', 'stops' => []], 'messages.itineraries.stops_required'],
        'day zero' => [['title' => 'x', 'stops' => [['placeId' => seed_id('seed-mina-passagem'), 'day' => 0, 'order' => 1]]], 'messages.itineraries.invalid_stop'],
        'same slot' => [['title' => 'x', 'stops' => [['placeId' => seed_id('seed-mina-passagem'), 'day' => 1, 'order' => 1], ['placeId' => seed_id('seed-igreja-pampulha'), 'day' => 1, 'order' => 1]]], 'messages.itineraries.duplicate_stop'],
    ]);

    it('writes nothing when one of the places does not exist', function () use ($create) {
        graphql($create, ['input' => ['title' => 'x', 'stops' => [
            ['placeId' => seed_id('seed-mina-passagem'), 'day' => 1, 'order' => 1],
            ['placeId' => 'nope', 'day' => 1, 'order' => 2],
        ]]], $this->token)->assertJsonPath('errors.0.extensions.key', 'messages.itineraries.invalid_place');

        expect(graphRow('MATCH (i:Itinerary) RETURN count(i) AS total'))->toBe(['total' => 0]);
    });
});

describe('recommendations', function () {
    it('recommends what people who visited the same places also visited, most shared first', function () {
        [$bruno, $carla] = [User::factory()->create(), User::factory()->create()];
        graph()->write(
            'UNWIND $visits AS visit
             MATCH (u:User {id: visit[0]}), (p:Place {id: visit[1]})
             CREATE (u)-[:VISITED {date: date()}]->(p)',
            ['visits' => [
                [$this->ana->id, seed_id('seed-igreja-sao-francisco-ouro-preto')],
                [$bruno->id, seed_id('seed-igreja-sao-francisco-ouro-preto')], [$bruno->id, seed_id('seed-mina-passagem')], [$bruno->id, seed_id('seed-igreja-pampulha')],
                [$carla->id, seed_id('seed-igreja-sao-francisco-ouro-preto')], [$carla->id, seed_id('seed-mina-passagem')],
            ]]
        );

        graphql('{ recommendations(limit: 5) { id } }', token: $this->token)
            ->assertJsonPath('data.recommendations', [['id' => seed_id('seed-mina-passagem')], ['id' => seed_id('seed-igreja-pampulha')]]);
    });

    it('is empty for a user without visits', function () {
        graphql('{ recommendations { id } }', token: $this->token)->assertJsonPath('data.recommendations', []);
    });
});
