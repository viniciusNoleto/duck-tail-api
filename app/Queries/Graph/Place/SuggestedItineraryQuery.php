<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class SuggestedItineraryQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Roteiro sugerido: lugares do tema (nome sem diferenciar maiúsculas) a até `$radius_m` do ponto de
     * partida, do mais perto ao mais longe — o próprio ponto de partida entra (distância 0) se for do tema.
     * `null` se o ponto de partida não existe.
     *
     * @param string $place_id
     * @param string $theme
     * @param float $radius_m
     * @return list<array{place: array<string, mixed>, distanceM: float}>|null
     */
    public function get(string $place_id, string $theme, float $radius_m): ?array {
        $rows = $this->graph->read(
            'MATCH (s:Place {id: $id})
             OPTIONAL MATCH (p:Place)-[:PART_OF]->(t:Theme)
             WHERE toLower(t.name) = toLower($theme) AND point.distance(p.location, s.location) <= $radius
             WITH DISTINCT s, p, point.distance(p.location, s.location) AS distance ORDER BY distance, p.id
             RETURN s.id AS start, collect(CASE WHEN p IS NULL THEN null ELSE {place: ' . Cypher::place('p') . ', distanceM: distance} END) AS stops',
            ['id' => $place_id, 'theme' => $theme, 'radius' => $radius_m]
        );

        return $rows === [] ? null : $rows[0]['stops'];
    }
}
