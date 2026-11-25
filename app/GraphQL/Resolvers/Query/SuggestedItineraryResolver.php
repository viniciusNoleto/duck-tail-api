<?php

namespace App\GraphQL\Resolvers\Query;

use App\Data\Graph\Common\RadiusDTO;
use App\Exceptions\ApiException;
use App\Queries\Graph\Place\SuggestedItineraryQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `suggestedItinerary(placeId, theme, radiusKm)`: raio de até 100 km; ponto de partida inexistente → NOT_FOUND.
 */
final class SuggestedItineraryResolver {

    public const DEFAULT_RADIUS_KM = 10;

    public const MAX_RADIUS_KM = 100;

    public function __construct(private readonly SuggestedItineraryQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     * @throws ApiException
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        $theme = trim((string)$args['theme']);

        if ($theme === '') {
            throw new ApiException('messages.places.theme_required', 422);
        }

        $radius = RadiusDTO::fromKm($args['radiusKm'] ?? null, self::DEFAULT_RADIUS_KM, self::MAX_RADIUS_KM);

        return $this->query->get((string)$args['placeId'], $theme, $radius->meters)
            ?? throw new ApiException('messages.places.not_found', 404);
    }
}
