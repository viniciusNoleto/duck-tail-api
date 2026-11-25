<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Data\Graph\Place\SearchPlacesDTO;

final class SearchPlacesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Com termo: índice de texto completo `place_search` (nome e descrição, sem acento): o nome igual ao
     * termo primeiro, depois o nome que começa com ele, depois a relevância (que já pesa mais o nome).
     * Sem termo: todos, por nome. Categoria (pelo nome ou pelo id) filtra nos dois casos. Busca `first + 1` para saber se há
     * próxima página sem contar o total.
     *
     * @param SearchPlacesDTO $dto
     * @return list<array<string, mixed>>
     */
    public function get(SearchPlacesDTO $dto): array {
        $category = 'WHERE ($category IS NULL OR EXISTS { (p)-[:HAS_CATEGORY]->(:Category {name: $category}) })
                     AND ($categoryId IS NULL OR EXISTS { (p)-[:HAS_CATEGORY]->(k:Category) WHERE k.id = $categoryId OR (k.id IS NULL AND k.name = $categoryId) })';
        $match = $dto->query !== null
            ? "CALL db.index.fulltext.queryNodes('place_search', \$query) YIELD node AS p, score {$category}
               WITH p, score, CASE WHEN toLower(p.name) = \$exact THEN 2 WHEN toLower(p.name) STARTS WITH \$exact THEN 1 ELSE 0 END AS band
               ORDER BY band DESC, score DESC, p.name, p.id"
            : "MATCH (p:Place) {$category} WITH p ORDER BY p.name, p.id";

        $rows = $this->graph->read("{$match} SKIP \$skip LIMIT \$limit RETURN " . Cypher::place('p') . ' AS place', [
            'query' => $dto->query,
            'exact' => $dto->exact,
            'category' => $dto->category,
            'categoryId' => $dto->category_id,
            'skip' => $dto->page->offset,
            'limit' => $dto->page->first + 1,
        ]);

        return array_column($rows, 'place');
    }
}
