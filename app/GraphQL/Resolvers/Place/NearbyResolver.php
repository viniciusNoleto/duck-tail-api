<?php

namespace App\GraphQL\Resolvers\Place;

use App\Base\GraphQL\BatchLoader;
use App\Data\Graph\Common\RadiusDTO;
use App\Queries\Graph\Place\PlacesNearPlacesQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Place.nearby(radiusKm)` (lote). Raio de até 20 km.
 */
final class NearbyResolver {

    public const DEFAULT_RADIUS_KM = 2;

    public const MAX_RADIUS_KM = 20;

    public function __construct(private readonly PlacesNearPlacesQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred {
        $radius = RadiusDTO::fromKm($args['radiusKm'] ?? null, self::DEFAULT_RADIUS_KM, self::MAX_RADIUS_KM);

        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($keys, $radius->meters))->load($root['id'], []);
    }
}
