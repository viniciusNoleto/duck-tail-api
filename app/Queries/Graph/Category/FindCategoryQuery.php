<?php

namespace App\Queries\Graph\Category;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class FindCategoryQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Categoria pelo id público (UUID); a que ainda não recebeu id é encontrada pelo nome.
     *
     * @param string $id
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array {
        $rows = $this->graph->read(
            'MATCH (c:Category) WHERE c.id = $id OR (c.id IS NULL AND c.name = $id)
             RETURN ' . Cypher::category('c') . ' AS category LIMIT 1',
            ['id' => $id]
        );

        return $rows[0]['category'] ?? null;
    }
}
