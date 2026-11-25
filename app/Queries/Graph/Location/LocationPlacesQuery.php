<?php

namespace App\Queries\Graph\Location;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Data\Graph\Location\LocationPlacesDTO;

final class LocationPlacesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Lugares da localidade e de todas as de baixo, uma página (`first + 1` itens, para saber se há
     * próxima). Sem termo: por nome. Com termo: índice `place_search` restrito à localidade, o nome igual ao
     * termo primeiro, depois o que começa com ele, depois a relevância. A categoria filtra nos dois casos.
     *
     * @param string $location_id
     * @param LocationPlacesDTO $dto
     * @return list<array<string, mixed>>
     */
    public function get(string $location_id, LocationPlacesDTO $dto): array {
        $within = 'EXISTS { (p)-[:LOCATED_IN]->(:Location)-[:WITHIN*0..8]->(:Location {id: $id}) }
                   AND ($categoryId IS NULL OR EXISTS { (p)-[:HAS_CATEGORY]->(k:Category) WHERE k.id = $categoryId OR (k.id IS NULL AND k.name = $categoryId) })';

        $match = $dto->query !== null
            ? "CALL db.index.fulltext.queryNodes('place_search', \$query) YIELD node AS p, score
               WHERE {$within}
               WITH p, score, CASE WHEN toLower(p.name) = \$exact THEN 2 WHEN toLower(p.name) STARTS WITH \$exact THEN 1 ELSE 0 END AS band
               ORDER BY band DESC, score DESC, p.name, p.id"
            : "MATCH (p:Place)-[:LOCATED_IN]->(:Location)-[:WITHIN*0..8]->(:Location {id: \$id})
               WITH DISTINCT p WHERE {$within}
               WITH p ORDER BY p.name, p.id";

        $rows = $this->graph->read("{$match} SKIP \$skip LIMIT \$limit RETURN " . Cypher::place('p') . ' AS place', [
            'id' => $location_id,
            'query' => $dto->query,
            'exact' => $dto->exact,
            'categoryId' => $dto->category_id,
            'skip' => $dto->page->offset,
            'limit' => $dto->page->first + 1,
        ]);

        return array_column($rows, 'place');
    }
}
