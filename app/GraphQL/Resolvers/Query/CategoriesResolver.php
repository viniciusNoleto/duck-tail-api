<?php

namespace App\GraphQL\Resolvers\Query;

use App\Data\Graph\Common\LimitDTO;
use App\Queries\Graph\Category\ListCategoriesQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `categories(limit)`: as categorias com mais lugares.
 */
final class CategoriesResolver {

    public function __construct(private readonly ListCategoriesQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        return $this->query->get(LimitDTO::fromArg($args['limit'] ?? null, 24)->limit);
    }
}
