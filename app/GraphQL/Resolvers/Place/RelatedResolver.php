<?php

namespace App\GraphQL\Resolvers\Place;

use App\Base\GraphQL\BatchLoader;
use App\Data\Graph\Common\LimitDTO;
use App\Queries\Graph\Place\RelatedPlacesQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Place.related(limit)` (lote).
 */
final class RelatedResolver {

    public function __construct(private readonly RelatedPlacesQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred {
        $limit = LimitDTO::fromArg($args['limit'] ?? null)->limit;

        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($keys, $limit))->load($root['id'], []);
    }
}
