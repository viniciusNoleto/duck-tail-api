<?php

namespace App\GraphQL\Resolvers\Query;

use App\Data\Graph\Place\FeaturedPlacesDTO;
use App\Queries\Graph\Place\FeaturedPlacesQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `featuredPlaces(locationId, limit)`: vitrine da página inicial e das localidades.
 */
final class FeaturedPlacesResolver {

    public function __construct(private readonly FeaturedPlacesQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        return $this->query->get(FeaturedPlacesDTO::fromArgs($args));
    }
}
