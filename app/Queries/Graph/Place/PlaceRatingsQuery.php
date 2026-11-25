<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Graph;

final class PlaceRatingsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.rating` e `Place.ratingCount` em lote: média (uma casa) e quantidade das notas de `VISITED`.
     *
     * @param list<string> $ids
     * @return array<string, array{rating: float|null, count: int}>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id
             OPTIONAL MATCH (:Place {id: id})<-[v:VISITED]-(:User) WHERE v.rating IS NOT NULL
             WITH id, avg(v.rating) AS average, count(v) AS total
             RETURN id, CASE WHEN total = 0 THEN null ELSE round(average, 1) END AS rating, total AS count',
            ['ids' => $ids]
        );

        $ratings = [];

        foreach ($rows as $row) {
            $ratings[$row['id']] = ['rating' => $row['rating'] === null ? null : (float)$row['rating'], 'count' => (int)$row['count']];
        }

        return $ratings;
    }
}
