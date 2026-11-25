<?php

namespace App\Queries\Graph\Tour;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class TourCompaniesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Tour.company` em lote (`OFFERS`).
     *
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (co:Company)-[:OFFERS]->(:Tour {id: id}) RETURN id, ' . Cypher::company('co') . ' AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
