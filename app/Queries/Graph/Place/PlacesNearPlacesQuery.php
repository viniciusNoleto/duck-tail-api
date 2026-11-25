<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class PlacesNearPlacesQuery {

    public const LIMIT = 30;

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.nearby` em lote: para cada lugar, os outros a até `$radius_m` (calculado na hora pelo índice
     * de ponto, então vale para qualquer raio, não só para as arestas `NEAR` já gravadas).
     *
     * @param list<string> $ids
     * @param float $radius_m
     * @return array<string, list<array{place: array<string, mixed>, distanceM: float}>>
     */
    public function get(array $ids, float $radius_m): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (s:Place {id: id})
             CALL (s) {
                 MATCH (p:Place) WHERE p <> s AND point.distance(p.location, s.location) <= $radius
                 WITH p, point.distance(p.location, s.location) AS distance ORDER BY distance, p.id LIMIT $limit
                 RETURN collect({place: ' . Cypher::place('p') . ', distanceM: distance}) AS nearby
             }
             RETURN id, nearby',
            ['ids' => $ids, 'radius' => $radius_m, 'limit' => self::LIMIT]
        );

        return array_column($rows, 'nearby', 'id');
    }
}
