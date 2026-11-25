<?php

namespace App\Queries\Graph\Category;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class ListCategoriesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Categorias com pelo menos um lugar, da que tem mais lugares para a que tem menos (empate por nome).
     *
     * @param int $limit
     * @return list<array<string, mixed>>
     */
    public function get(int $limit): array {
        $rows = $this->graph->read(
            'MATCH (c:Category)
             WITH c, COUNT { (c)<-[:HAS_CATEGORY]-(:Place) } AS places WHERE places > 0
             ORDER BY places DESC, c.name LIMIT $limit
             RETURN ' . Cypher::category('c') . ' AS category',
            ['limit' => $limit]
        );

        return array_column($rows, 'category');
    }
}
