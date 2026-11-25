<?php

namespace App\Actions\Graph\Relate;

use App\Base\Graph\Graph;
use App\Enum\Graph\RelationReasonEnum;

final class RelatePlacesAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Recalcula as relações automáticas `(Place)-[:RELATED_TO {kind, value}]->(Place)`: mesmo autor
     * (`authors`, P84 do Wikidata, em qualquer lugar) e, quando não há autor em comum, mesmo estilo
     * (`styles`, P149) **na mesma localidade** — estilo é comum demais (centenas de igrejas barrocas num estado):
     * sem esse corte, cada estilo viraria um grafo completo de milhares de arestas sem informação.
     * Uma aresta por par, do menor `id` para o maior, gravadas em lotes (uma transação por lote). As de
     * curadoria (`kind: 'curated'`) não são tocadas.
     *
     * @return int arestas gravadas
     */
    public function handle(): int {
        $this->graph->writeUntilDone(
            'MATCH (:Place)-[r:RELATED_TO]->(:Place) WHERE r.kind IN $automatic WITH r LIMIT $limit DELETE r RETURN count(*) AS affected',
            ['automatic' => [RelationReasonEnum::AUTHOR->value, RelationReasonEnum::STYLE->value]]
        );

        $ids = array_column($this->graph->read(
            'MATCH (a:Place) WHERE size(coalesce(a.authors, [])) > 0 OR size(coalesce(a.styles, [])) > 0 RETURN a.id AS id ORDER BY id'
        ), 'id');

        return $this->graph->writeInBatches(
            'UNWIND $keys AS id MATCH (a:Place {id: id})
             CALL (a) {
                 MATCH (b:Place) WHERE a.id < b.id
                 WITH a, b,
                      [x IN coalesce(a.authors, []) WHERE x IN coalesce(b.authors, [])] AS authors,
                      CASE WHEN EXISTS { (a)-[:LOCATED_IN]->(:Location)<-[:LOCATED_IN]-(b) }
                           THEN [x IN coalesce(a.styles, []) WHERE x IN coalesce(b.styles, [])] ELSE [] END AS styles
                 WHERE (size(authors) > 0 OR size(styles) > 0)
                   AND NOT EXISTS { (a)-[:RELATED_TO {kind: $curated}]-(b) }
                 MERGE (a)-[r:RELATED_TO]->(b)
                 SET r.kind = CASE WHEN size(authors) > 0 THEN $author ELSE $style END,
                     r.value = CASE WHEN size(authors) > 0 THEN authors[0] ELSE styles[0] END
                 RETURN count(r) AS edges
             }
             RETURN sum(edges) AS affected',
            $ids,
            [
                'author' => RelationReasonEnum::AUTHOR->value,
                'style' => RelationReasonEnum::STYLE->value,
                'curated' => RelationReasonEnum::CURATED->value,
            ]
        );
    }
}
