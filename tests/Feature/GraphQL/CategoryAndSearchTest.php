<?php

use App\Actions\Graph\Category\AssignCategoryIdsAction;
use Database\Seeders\GraphSeeder;

/*
| Categorias como entidade (id, foto, contagem) e a busca geral `search`, contra o grafo do `GraphSeeder`.
*/

beforeEach(fn() => $this->seed(GraphSeeder::class));

/** Id público (UUID) da categoria de nome `$name`. */
function categoryIdOf(string $name): string {
    return (string)graphRow('MATCH (c:Category {name: $name}) RETURN c.id AS id', ['name' => $name])['id'];
}

const CATEGORY_UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

describe('categories', function () {
    it('gives every seeded category an opaque uuid id, never the name', function () {
        $ids = array_column(graph()->read('MATCH (c:Category) RETURN c.id AS id'), 'id');

        expect($ids)->not->toBeEmpty()->each->toMatch(CATEGORY_UUID)
            ->and(array_unique($ids))->toHaveCount(count($ids));
    });

    it('returns a category by id with its place count and the photo of its most sought place', function () {
        graph()->write('MATCH (p:Place {id: "' . seed_id('seed-museu-inconfidencia') . '"}) SET p.image = "https://example.org/museu.jpg"');

        $id = categoryIdOf('museu');

        graphql('query ($id: ID!) { category(id: $id) { id name placeCount image } }', ['id' => $id])->assertOk()->assertExactJson(['data' => ['category' => [
            'id' => $id, 'name' => 'museu', 'placeCount' => 1, 'image' => 'https://example.org/museu.jpg',
        ]]]);
    });

    it('returns the name of the category in every language of the API, falling back to pt-br', function () {
        graph()->write('MERGE (p:Place {id: "x", name: "x"}) MERGE (k:Category {name: "chafariz", id: "00000000-0000-0000-0000-000000000001"}) MERGE (p)-[:HAS_CATEGORY]->(k)');

        expect(graphql('query ($id: ID!) { category(id: $id) { name names { ptBr esMx enUs } } }', ['id' => categoryIdOf('igreja')])->json('data.category'))
            ->toBe(['name' => 'igreja', 'names' => ['ptBr' => 'igreja', 'esMx' => 'iglesia', 'enUs' => 'church']])
            ->and(graphql('{ category(id: "00000000-0000-0000-0000-000000000001") { names { ptBr esMx enUs } } }')->json('data.category.names'))
            ->toBe(['ptBr' => 'chafariz', 'esMx' => 'chafariz', 'enUs' => 'chafariz'])
            ->and(graphql('{ search(term: "iglesia") { kind } }')->json('data.search'))->toBe([])
            ->and(collect(graphql('{ search(term: "igreja") { kind names { enUs } } }')->json('data.search'))->firstWhere('kind', 'category'))
            ->toBe(['kind' => 'category', 'names' => ['enUs' => 'church']]);
    });

    it('gives every category an Iconify icon picked by its name, with a pin when no keyword matches', function () {
        graph()->write('MERGE (p:Place {id: "x", name: "x"}) MERGE (k:Category {name: "objeto zamenhof-esperanto", id: "00000000-0000-0000-0000-000000000002"}) MERGE (p)-[:HAS_CATEGORY]->(k)');

        expect(graphql('query ($id: ID!) { category(id: $id) { icon } }', ['id' => categoryIdOf('igreja')])->json('data.category.icon'))->toBe('tabler:building-church')
            ->and(graphql('{ category(id: "00000000-0000-0000-0000-000000000002") { icon } }')->json('data.category.icon'))->toBe('tabler:map-pin')
            ->and(collect(graphql('{ categories(limit: 50) { icon } }')->json('data.categories'))->pluck('icon')->every(fn(string $icon) => str_contains($icon, ':')))->toBeTrue();
    });

    it('returns null for an unknown category and a null image when no place has a photo', function () {
        graphql('{ category(id: "nope") { id } }')->assertExactJson(['data' => ['category' => null]]);
        graph()->write('MATCH (p:Place) SET p.image = null');

        expect(graphql('query ($id: ID!) { category(id: $id) { image placeCount } }', ['id' => categoryIdOf('igreja')])->json('data.category'))
            ->toBe(['image' => null, 'placeCount' => 7]);
    });

    it('prefers the curated photo of the category', function () {
        graph()->write('MATCH (c:Category {name: "igreja"}) SET c.image = "https://example.org/igreja.jpg"');

        expect(graphql('query ($id: ID!) { category(id: $id) { image } }', ['id' => categoryIdOf('igreja')])->json('data.category.image'))
            ->toBe('https://example.org/igreja.jpg');
    });

    it('lists categories with the most places first and validates the limit', function () {
        $categories = graphql('{ categories(limit: 2) { id placeCount } }')->json('data.categories');

        expect($categories[0])->toBe(['id' => categoryIdOf('igreja'), 'placeCount' => 7])->and($categories)->toHaveCount(2);

        graphql('{ categories(limit: 51) { id } }')->assertJsonPath('errors.0.extensions.code', 'VALIDATION');
    });

    it('exposes the category id on places and filters places by it', function () {
        expect(graphql('{ place(id: "' . seed_id('seed-museu-inconfidencia') . '") { categories { id name } } }')->json('data.place.categories'))
            ->toBe([['id' => categoryIdOf('museu'), 'name' => 'museu']]);

        $ids = array_column(array_column(graphql('query ($id: ID!) { searchPlaces(categoryId: $id, first: 50) { edges { node { id } } } }', ['id' => categoryIdOf('igreja')])
            ->json('data.searchPlaces.edges'), 'node'), 'id');

        expect($ids)->toHaveCount(7);
    });

    it('gives a uuid to categories without id, replaces the old slug ids and keeps existing uuids', function () {
        $kept = categoryIdOf('museu');
        graph()->write('CREATE (:Category {name: "Museu"}), (:Category {name: "trem", id: "trem"})');

        expect(app(AssignCategoryIdsAction::class)->handle())->toBe(2)
            ->and(app(AssignCategoryIdsAction::class)->handle())->toBe(0)
            ->and(categoryIdOf('Museu'))->toMatch(CATEGORY_UUID)
            ->and(categoryIdOf('trem'))->toMatch(CATEGORY_UUID)
            ->and(categoryIdOf('museu'))->toBe($kept);
    });

    it('creates imported categories with a uuid right away', function () {
        graphPlace('p-1', ['categories' => ['farol']]);

        expect(categoryIdOf('farol'))->toMatch(CATEGORY_UUID);
    });

    it('finds a category that has no id yet by its name', function () {
        graph()->write('CREATE (:Place {id: "p-2", name: "Farol"})-[:HAS_CATEGORY]->(:Category {name: "farol velho"})');

        graphql('{ category(id: "farol velho") { id name placeCount } }')
            ->assertExactJson(['data' => ['category' => ['id' => 'farol velho', 'name' => 'farol velho', 'placeCount' => 1]]]);
    });
});

describe('search(term)', function () {
    it('mixes kinds, with the exact name first and categories and locations before places', function () {
        $results = graphql('{ search(term: "ouro preto") { kind id name subtype context count } }')->json('data.search');

        expect($results[0])->toBe(['kind' => 'location', 'id' => seed_id('seed-ouro-preto'), 'name' => 'Ouro Preto', 'subtype' => 'city', 'context' => 'Minas Gerais', 'count' => null])
            ->and(array_column($results, 'kind'))->toContain('tour')
            ->and(collect($results)->firstWhere('kind', 'tour')['context'])->toBe('Trilhas do Ouro Turismo');
    });

    it('finds categories with their place count, companies with their base and places with their city', function () {
        $museum = graphql('{ search(term: "museu") { kind id count } }')->json('data.search.0');
        $company = collect(graphql('{ search(term: "trilhas") { kind id subtype context } }')->json('data.search'))->firstWhere('kind', 'company');
        $place = collect(graphql('{ search(term: "inconfidencia") { kind id context } }')->json('data.search'))->firstWhere('kind', 'place');

        expect($museum)->toBe(['kind' => 'category', 'id' => categoryIdOf('museu'), 'count' => 1])
            ->and($company)->toBe(['kind' => 'company', 'id' => seed_id('seed-trilhas-do-ouro'), 'subtype' => 'agency', 'context' => 'Ouro Preto'])
            ->and($place)->toBe(['kind' => 'place', 'id' => seed_id('seed-museu-inconfidencia'), 'context' => 'Ouro Preto']);
    });

    it('caps the results and ignores a blank term', function () {
        expect(graphql('{ search(term: "o", limit: 3) { id } }')->json('data.search'))->toHaveCount(3)
            ->and(graphql('{ search(term: "   ") { id } }')->json('data.search'))->toBe([]);
    });

    it('validates the limit and the term length', function () {
        graphql('{ search(term: "ouro", limit: 21) { id } }')->assertJsonPath('errors.0.extensions.key', 'messages.places.invalid_limit');
        graphql('query ($t: String!) { search(term: $t) { id } }', ['t' => str_repeat('a', 101)])
            ->assertJsonPath('errors.0.extensions.key', 'messages.places.invalid_search');
    });

    it('treats Lucene syntax in the term as text', function () {
        graphql('{ search(term: "igreja (\"matriz\" AND *") { id } }')->assertOk()->assertJsonMissingPath('errors');
    });
});
