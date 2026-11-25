<?php

namespace App\GraphQL\Resolvers\Query;

use App\Data\Graph\Location\ListLocationsDTO;
use App\Queries\Graph\Location\ListLocationsQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `locations(parentId, kind, limit)`.
 */
final class LocationsResolver {

    public function __construct(private readonly ListLocationsQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        return $this->query->get(ListLocationsDTO::fromArgs($args));
    }
}
