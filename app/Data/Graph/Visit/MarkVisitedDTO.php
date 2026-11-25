<?php

namespace App\Data\Graph\Visit;

use App\Base\GraphQL\ArgsValidator;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

class MarkVisitedDTO extends Data {

    public function __construct(
        public string $place_id,
        public ?int $rating,
    ) {
    }

    /**
     * @param array<string, mixed> $args
     * @return MarkVisitedDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): MarkVisitedDTO {
        $validated = ArgsValidator::validate($args, [
            'placeId' => 'required|string|max:100',
            'rating' => 'nullable|integer|between:1,5',
        ], [
            'placeId.*' => 'messages.places.not_found',
            'rating.*' => 'messages.visits.invalid_rating',
        ]);

        return new self(
            place_id: (string)$validated['placeId'],
            rating: isset($validated['rating']) ? (int)$validated['rating'] : null,
        );
    }
}
