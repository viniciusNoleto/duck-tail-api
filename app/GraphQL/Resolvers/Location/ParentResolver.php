<?php

namespace App\GraphQL\Resolvers\Location;

use App\Base\GraphQL\BatchLoader;
use App\Queries\Graph\Location\LocationParentsQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Location.parent` (lote).
 */
final class ParentResolver {

    public function __construct(private readonly LocationParentsQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred {
        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($keys))->load($root['id'], null);
    }
}
