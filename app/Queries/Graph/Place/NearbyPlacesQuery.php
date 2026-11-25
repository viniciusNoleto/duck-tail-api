<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class NearbyPlacesQuery {

    public const LIMIT = 50;

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Lugares a até `$radius_m` de um ponto, do mais perto ao mais longe (índice de ponto `place_location`).
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radius_m
     * @return list<array{place: array<string, mixed>, distanceM: float}>
     */
    public function get(float $latitude, float $longitude, float $radius_m): array {
        /** @var list<array{place: array<string, mixed>, distanceM: float}> $rows */
        $rows = $this->graph->read(
            'WITH point({latitude: $latitude, longitude: $longitude}) AS origin
             MATCH (p:Place) WHERE point.distance(p.location, origin) <= $radius
             WITH p, point.distance(p.location, origin) AS distance ORDER BY distance, p.id LIMIT $limit
             RETURN ' . Cypher::place('p') . ' AS place, distance AS distanceM',
            ['latitude' => $latitude, 'longitude' => $longitude, 'radius' => $radius_m, 'limit' => self::LIMIT]
        );

        return $rows;
    }
}
