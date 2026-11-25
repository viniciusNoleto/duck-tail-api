<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Data\Graph\Place\FeaturedPlacesDTO;

final class FeaturedPlacesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Lugares com foto (da localidade e das de baixo, ou de todo o grafo): os de melhor nota média
     * primeiro (sem nota por último), depois os mais procurados (cada passeio que para no lugar vale 10,
     * cada visita 5, cada ligação `RELATED_TO` 1). Empate por nome.
     *
     * @param FeaturedPlacesDTO $dto
     * @return list<array<string, mixed>>
     */
    public function get(FeaturedPlacesDTO $dto): array {
        $rows = $this->graph->read(
            'MATCH (p:Place) WHERE p.image IS NOT NULL
               AND ($location IS NULL OR EXISTS { (p)-[:LOCATED_IN]->(:Location)-[:WITHIN*0..8]->(:Location {id: $location}) })
             WITH p, COUNT { (p)<-[:STOPS_AT]-(:Tour) } * 10 + COUNT { (p)<-[:VISITED]-(:User) } * 5 + COUNT { (p)-[:RELATED_TO]-(:Place) } AS score,
                  COLLECT { MATCH (p)<-[v:VISITED]-(:User) WHERE v.rating IS NOT NULL RETURN v.rating } AS ratings
             WITH p, score, CASE WHEN size(ratings) = 0 THEN null ELSE round(reduce(total = 0.0, r IN ratings | total + r) / size(ratings), 1) END AS rating
             ORDER BY rating IS NULL, rating DESC, score DESC, p.name, p.id LIMIT $limit
             RETURN ' . Cypher::place('p') . ' AS place',
            ['location' => $dto->location_id, 'limit' => $dto->limit]
        );

        return array_column($rows, 'place');
    }
}
