<?php

namespace App\Data\Graph\Import;

use Spatie\LaravelData\Data;

/**
 * Um lugar vindo do Wikidata, já normalizado para o grafo (`ImportPlacesAction`), com a cadeia de
 * localidades em que fica: país → subdivisão (estado, província…) → cidade.
 */
class ImportedPlaceDTO extends Data {

    /**
     * @param string $wikidata_id
     * @param string $name
     * @param string|null $description
     * @param float $latitude
     * @param float $longitude
     * @param string|null $image
     * @param string $city_wikidata_id
     * @param string $city_name
     * @param float|null $city_latitude
     * @param float|null $city_longitude
     * @param string $state_wikidata_id
     * @param string $state_name
     * @param string|null $country_wikidata_id
     * @param string|null $country_name
     * @param list<array{name: string, name_es: string|null, name_en: string|null}> $categories nome em pt-br, es e en
     * @param list<string> $authors
     * @param list<string> $styles
     */
    public function __construct(
        public string $wikidata_id,
        public string $name,
        public ?string $description,
        public float $latitude,
        public float $longitude,
        public ?string $image,
        public string $city_wikidata_id,
        public string $city_name,
        public ?float $city_latitude,
        public ?float $city_longitude,
        public string $state_wikidata_id,
        public string $state_name,
        public ?string $country_wikidata_id,
        public ?string $country_name,
        public array $categories,
        public array $authors,
        public array $styles,
    ) {
    }
}
