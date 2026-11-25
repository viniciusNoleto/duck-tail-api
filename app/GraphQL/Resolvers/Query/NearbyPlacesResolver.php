<?php

namespace App\GraphQL\Resolvers\Query;

use App\Data\Graph\Place\NearbyPlacesDTO;
use App\Queries\Graph\Place\NearbyPlacesQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `nearbyPlaces(lat, lng, radiusKm)`: "próximos de mim".
 */
final class NearbyPlacesResolver {

    public function __construct(private readonly NearbyPlacesQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        $dto = NearbyPlacesDTO::fromArgs($args);

        return $this->query->get($dto->latitude, $dto->longitude, $dto->radius->meters);
    }
}
