<?php

namespace App\Queries\Graph\Tour;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Data\Graph\Tour\ToursThroughDTO;

final class ToursThroughQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Passeios com parada em pelo menos um dos lugares pedidos: primeiro os que cobrem todos, depois os
     * que cobrem mais, depois por nome. Ids que não existem simplesmente não casam.
     *
     * @param ToursThroughDTO $dto
     * @return list<array{tour: array<string, mixed>, matchedPlaceIds: list<string>, coversAll: bool}>
     */
    public function get(ToursThroughDTO $dto): array {
        return $this->graph->read(
            'MATCH (t:Tour)-[:STOPS_AT]->(p:Place) WHERE p.id IN $ids
             WITH t, collect(DISTINCT p.id) AS matched
             WITH t, matched, size(matched) = size($ids) AS coversAll
             ORDER BY coversAll DESC, size(matched) DESC, t.name, t.id LIMIT $limit
             RETURN ' . Cypher::tour('t') . ' AS tour, matched AS matchedPlaceIds, coversAll',
            ['ids' => $dto->place_ids, 'limit' => $dto->limit]
        );
    }
}
