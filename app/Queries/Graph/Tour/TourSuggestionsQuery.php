<?php

namespace App\Queries\Graph\Tour;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Data\Graph\Tour\TourSuggestionsDTO;

final class TourSuggestionsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Paradas de passeio ainda não escolhidas, na ordem pedida pelo buscador: mais passeios em comum com
     * os escolhidos, com foto antes de sem foto, maior nota média (sem nota por último), mais passeios;
     * empate por nome.
     *
     * @param TourSuggestionsDTO $dto
     * @return list<array<string, mixed>>
     */
    public function get(TourSuggestionsDTO $dto): array {
        $rows = $this->graph->read(
            'MATCH (p:Place) WHERE EXISTS { (p)<-[:STOPS_AT]-(:Tour) } AND NOT p.id IN $chosen
             CALL (p) {
                 OPTIONAL MATCH (p)<-[v:VISITED]-(:User) WHERE v.rating IS NOT NULL
                 RETURN avg(v.rating) AS rating
             }
             WITH p, rating,
                  COUNT { (p)<-[:STOPS_AT]-(t:Tour) WHERE EXISTS { (t)-[:STOPS_AT]->(c:Place) WHERE c.id IN $chosen } } AS together,
                  COUNT { (p)<-[:STOPS_AT]-(:Tour) } AS tours
             ORDER BY together DESC, CASE WHEN p.image IS NULL THEN 1 ELSE 0 END, coalesce(rating, 0) DESC, tours DESC, p.name, p.id
             LIMIT $limit
             RETURN ' . Cypher::place('p') . ' AS place',
            ['chosen' => $dto->place_ids, 'limit' => $dto->limit]
        );

        return array_column($rows, 'place');
    }
}
