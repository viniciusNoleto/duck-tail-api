<?php

namespace App\Base\GraphQL;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\ResolverProvider as LighthouseResolverProvider;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;

/**
 * Liga cada campo do schema à classe `App\GraphQL\Resolvers\{Tipo}\{Campo}Resolver` (ex.: `Query.place` →
 * `Resolvers\Query\PlaceResolver`, `Place.related` → `Resolvers\Place\RelatedResolver`), com `__invoke`.
 *
 * Por que não as diretivas do Lighthouse (`@field`, `@guard`…): `graphql/schema.graphql` é o contrato que
 * o web lê com o graphql-codegen, e diretivas desconhecidas quebrariam a geração dos tipos. Assim o
 * arquivo fica SDL puro e a ligação é por convenção, como os controllers do REST.
 *
 * Campo de `Query`/`Mutation` sem classe é erro de definição (aparece ao montar o schema, e o teste
 * `GraphQL/SchemaTest` monta). Campo de outro tipo sem classe lê a chave de mesmo nome do array do pai.
 */
final class ResolverProvider implements ProvidesResolver {

    public const NAMESPACE = 'App\\GraphQL\\Resolvers';

    /**
     * @param FieldValue $fieldValue
     * @return \Closure
     */
    public function provideResolver(FieldValue $fieldValue): \Closure {
        $class = self::resolverClass($fieldValue->getParentName(), $fieldValue->getFieldName());

        if (class_exists($class)) {
            $resolver = Container::getInstance()->make($class);

            return \Closure::fromCallable([$resolver, '__invoke']);
        }

        if (RootType::isRootType($fieldValue->getParentName())) {
            throw new DefinitionException("Missing resolver {$class} for {$fieldValue->getParentName()}.{$fieldValue->getFieldName()}.");
        }

        return (new LighthouseResolverProvider)->provideResolver($fieldValue);
    }

    /**
     * @param string $type
     * @param string $field
     * @return string
     */
    public static function resolverClass(string $type, string $field): string {
        return self::NAMESPACE . '\\' . $type . '\\' . Str::studly($field) . 'Resolver';
    }
}
