<?php

namespace App\Data\Graph\Place;

use App\Base\GraphQL\ArgsValidator;
use App\Data\Graph\Common\RadiusDTO;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

class NearbyPlacesDTO extends Data {

    public const DEFAULT_RADIUS_KM = 5;

    public const MAX_RADIUS_KM = 50;

    public function __construct(
        public float $latitude,
        public float $longitude,
        public RadiusDTO $radius,
    ) {
    }

    /**
     * @param array<string, mixed> $args
     * @return NearbyPlacesDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): NearbyPlacesDTO {
        $validated = ArgsValidator::validate($args, [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ], [
            '*' => 'messages.places.invalid_coordinates',
        ]);

        return new self(
            latitude: (float)$validated['lat'],
            longitude: (float)$validated['lng'],
            radius: RadiusDTO::fromKm($args['radiusKm'] ?? null, self::DEFAULT_RADIUS_KM, self::MAX_RADIUS_KM),
        );
    }
}
