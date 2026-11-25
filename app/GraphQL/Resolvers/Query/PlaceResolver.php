<?php

namespace App\GraphQL\Resolvers\Query;

use App\Queries\Graph\Place\FindPlaceQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `place(id)`: null quando não existe.
 */
final class PlaceResolver {

    public function __construct(private readonly FindPlaceQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string, mixed>|null
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): ?array {
        return $this->query->get((string)$args['id']);
    }
}
