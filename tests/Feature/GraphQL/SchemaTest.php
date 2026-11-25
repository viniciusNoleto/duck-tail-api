<?php

use App\Base\GraphQL\ResolverProvider;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

/*
| `graphql/schema.graphql` é o contrato com o web (graphql-codegen lê o arquivo direto).
*/

it('builds and validates the schema with every resolver in place', function () {
    $schema = app(SchemaBuilder::class)->schema();

    $schema->assertValid();

    expect($schema->getQueryType()->getFieldNames())->toContain('place', 'searchPlaces', 'me')
        ->and($schema->getMutationType()->getFieldNames())->toContain('markVisited', 'createItinerary');
});

it('keeps the contract file free of Lighthouse directives, so codegen reads it as plain SDL', function () {
    $document = Parser::parse(file_get_contents(base_path('graphql/schema.graphql')));

    expect(file_get_contents(base_path('graphql/schema.graphql')))->not->toMatch('/^[^#"]*@\w+/m')
        ->and($document->definitions->count())->toBeGreaterThan(10);
});

it('has a resolver class for every Query and Mutation field', function () {
    $document = Parser::parse(file_get_contents(base_path('graphql/schema.graphql')));
    $missing = [];

    foreach ($document->definitions as $definition) {
        if (!$definition instanceof ObjectTypeDefinitionNode || !in_array($definition->name->value, ['Query', 'Mutation'], true)) {
            continue;
        }

        foreach ($definition->fields as $field) {
            $class = ResolverProvider::resolverClass($definition->name->value, $field->name->value);

            if (!class_exists($class)) {
                $missing[] = $class;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('rejects a document deeper than the configured limit', function () {
    $nested = str_repeat('related { place { ', 7) . 'id' . str_repeat(' } }', 7);

    graphql("{ place(id: \"x\") { {$nested} } }")
        ->assertOk()
        ->assertJsonPath('errors.0.message', fn(string $message) => str_contains($message, 'depth'));
});
