<?php

namespace App\Data\Graph\Itinerary;

use App\Base\GraphQL\ArgsValidator;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

class CreateItineraryDTO extends Data {

    public const MAX_STOPS = 100;

    /**
     * @param string $title
     * @param bool $is_public
     * @param list<ItineraryStopDTO> $stops
     */
    public function __construct(
        public string $title,
        public bool $is_public,
        public array $stops,
    ) {
    }

    /**
     * Toda parada é validada (nenhuma é descartada em silêncio); dia + ordem não se repetem. A existência
     * dos lugares é conferida na Action, na mesma transação da gravação.
     *
     * @param array<string, mixed> $input
     * @return CreateItineraryDTO
     * @throws ApiException
     */
    public static function fromArgs(array $input): CreateItineraryDTO {
        $validated = ArgsValidator::validate($input, [
            'title' => 'required|string|max:150',
            'isPublic' => 'nullable|boolean',
            'stops' => 'required|array|min:1|max:' . self::MAX_STOPS,
            'stops.*.placeId' => 'required|string|max:100',
            'stops.*.day' => 'required|integer|min:1|max:365',
            'stops.*.order' => 'required|integer|min:1|max:1000',
        ], [
            'title.*' => 'messages.itineraries.title_required',
            'stops.*' => 'messages.itineraries.stops_required',
            'stops.*.*.*' => 'messages.itineraries.invalid_stop',
        ], ['max' => self::MAX_STOPS]);

        $title = trim((string)$validated['title']);

        if ($title === '') {
            throw new ApiException('messages.itineraries.title_required', 422);
        }

        $stops = array_map(fn(array $stop) => new ItineraryStopDTO(
            place_id: (string)$stop['placeId'],
            day: (int)$stop['day'],
            order: (int)$stop['order'],
        ), array_values($validated['stops']));

        $positions = array_map(fn(ItineraryStopDTO $stop) => "{$stop->day}:{$stop->order}", $stops);

        if (count(array_unique($positions)) !== count($positions)) {
            throw new ApiException('messages.itineraries.duplicate_stop', 422);
        }

        return new self(title: $title, is_public: (bool)($validated['isPublic'] ?? false), stops: $stops);
    }
}
