<?php

namespace App\Queries\Graph\Company;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;

final class FindCompanyQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * @param string $id
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array {
        $rows = $this->graph->read('MATCH (co:Company {id: $id}) RETURN ' . Cypher::company('co') . ' AS company', ['id' => $id]);

        return $rows[0]['company'] ?? null;
    }
}
