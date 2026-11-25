<?php

namespace App\GraphQL\Resolvers\Query;

use App\Data\Graph\Tour\ToursThroughDTO;
use App\Queries\Graph\Tour\ToursThroughQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `toursThrough(placeIds, limit)`: "quero passar por A, B e C".
 */
final class ToursThroughResolver {

    public function __construct(private readonly ToursThroughQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        return $this->query->get(ToursThroughDTO::fromArgs($args));
    }
}
