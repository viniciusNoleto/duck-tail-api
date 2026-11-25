<?php

namespace App\Data\Graph\Place;

use App\Base\GraphQL\ArgsValidator;
use App\Data\Graph\Common\LimitDTO;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

class FeaturedPlacesDTO extends Data {

    public function __construct(
        public ?string $location_id,
        public int $limit,
    ) {
    }

    /**
     * `locationId` em branco = o grafo todo; `limit` 1 a 50.
     *
     * @param array<string, mixed> $args
     * @return FeaturedPlacesDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): FeaturedPlacesDTO {
        $validated = ArgsValidator::validate($args, [
            'locationId' => 'nullable|string|max:64',
        ], [
            'locationId.*' => 'messages.locations.invalid_parent',
        ]);

        $location = trim((string)($validated['locationId'] ?? ''));

        return new self(
            location_id: $location !== '' ? $location : null,
            limit: LimitDTO::fromArg($args['limit'] ?? null, 12)->limit,
        );
    }
}
