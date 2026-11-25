<?php

namespace App\Queries\Graph\Category;

use App\Base\Graph\Graph;

final class CategoryImagesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Category.image` em lote: a foto gravada na categoria (`image`, curadoria) ou a do lugar mais procurado
     * dela que tem foto (o mesmo peso de `featuredPlaces`: passeio 10, visita 5, ligação 1). Sem nenhuma, null.
     *
     * @param list<string> $ids
     * @return array<string, string|null>
     */
    public function get(array $ids): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (c:Category) WHERE c.id = id OR (c.id IS NULL AND c.name = id)
             OPTIONAL CALL (c) {
                 MATCH (c)<-[:HAS_CATEGORY]-(p:Place) WHERE p.image IS NOT NULL
                 WITH p, COUNT { (p)<-[:STOPS_AT]-(:Tour) } * 10 + COUNT { (p)<-[:VISITED]-(:User) } * 5 + COUNT { (p)-[:RELATED_TO]-(:Place) } AS score
                 ORDER BY score DESC, p.name, p.id LIMIT 1
                 RETURN p.image AS top
             }
             RETURN id, coalesce(c.image, top) AS value',
            ['ids' => $ids]
        );

        return array_column($rows, 'value', 'id');
    }
}
