<?php

namespace App\Data\Graph\Tour;

use App\Base\GraphQL\ArgsValidator;
use App\Data\Graph\Common\LimitDTO;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

class ToursThroughDTO extends Data {

    public const MAX_PLACES = 10;

    /**
     * @param list<string> $place_ids sem repetição
     * @param int $limit
     */
    public function __construct(
        public array $place_ids,
        public int $limit,
    ) {
    }

    /**
     * 1 a 10 ids de lugar (repetidos contam uma vez).
     *
     * @param array<string, mixed> $args
     * @return ToursThroughDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): ToursThroughDTO {
        $validated = ArgsValidator::validate($args, [
            'placeIds' => 'required|array|min:1|max:' . self::MAX_PLACES,
            'placeIds.*' => 'required|string|max:64',
        ], [
            'placeIds' => 'messages.tours.invalid_places',
            'placeIds.*' => 'messages.tours.invalid_places',
        ], ['max' => self::MAX_PLACES]);

        $ids = array_values(array_unique(array_filter(array_map('trim', $validated['placeIds']), fn(string $id) => $id !== '')));

        if ($ids === []) {
            throw new ApiException('messages.tours.invalid_places', 422, ['max' => self::MAX_PLACES]);
        }

        return new self(place_ids: $ids, limit: LimitDTO::fromArg($args['limit'] ?? null, 20)->limit);
    }
}
