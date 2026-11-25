<?php

namespace App\GraphQL\Resolvers\Place;

use App\Base\GraphQL\BatchLoader;
use App\Queries\Graph\Place\PlaceRatingsQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Place.rating` (lote, `PlaceRatingsQuery`).
 */
final class RatingResolver {

    public function __construct(private readonly PlaceRatingsQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred {
        return BatchLoader::for($info, $args, fn(array $keys) => array_map(fn(array $rating) => $rating['rating'], $this->query->get($keys)))
            ->load($root['id'], null);
    }
}
