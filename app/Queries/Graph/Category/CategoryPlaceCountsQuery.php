<?php

namespace App\Queries\Graph\Category;

use App\Base\Graph\Graph;

final class CategoryPlaceCountsQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Category.placeCount` em lote.
     *
     * @param list<string> $ids
     * @return array<string, int>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (c:Category) WHERE c.id = id OR (c.id IS NULL AND c.name = id)
             RETURN id, COUNT { (c)<-[:HAS_CATEGORY]-(:Place) } AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
