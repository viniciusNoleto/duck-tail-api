<?php

namespace App\Data\Graph\Itinerary;

use Spatie\LaravelData\Data;

class ItineraryStopDTO extends Data {

    public function __construct(
        public string $place_id,
        public int $day,
        public int $order,
    ) {
    }
}
