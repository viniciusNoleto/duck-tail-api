<?php

namespace App\Data\Graph\Tour;

use App\Base\GraphQL\ArgsValidator;
use App\Data\Graph\Common\LimitDTO;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

class TourSuggestionsDTO extends Data {

    /**
     * @param list<string> $place_ids já escolhidos (sem repetição)
     * @param int $limit
     */
    public function __construct(
        public array $place_ids,
        public int $limit,
    ) {
    }

    /**
     * 0 a 10 ids de lugar; `limit` 1 a 50.
     *
     * @param array<string, mixed> $args
     * @return TourSuggestionsDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): TourSuggestionsDTO {
        $validated = ArgsValidator::validate(['placeIds' => $args['placeIds'] ?? []], [
            'placeIds' => 'present|array|max:' . ToursThroughDTO::MAX_PLACES,
            'placeIds.*' => 'required|string|max:64',
        ], [
            'placeIds' => 'messages.tours.invalid_places',
            'placeIds.*' => 'messages.tours.invalid_places',
        ], ['max' => ToursThroughDTO::MAX_PLACES]);

        return new self(
            place_ids: array_values(array_unique(array_filter(array_map('trim', $validated['placeIds']), fn(string $id) => $id !== ''))),
            limit: LimitDTO::fromArg($args['limit'] ?? null, 8)->limit,
        );
    }
}
