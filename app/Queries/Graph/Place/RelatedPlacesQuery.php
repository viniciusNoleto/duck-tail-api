<?php

namespace App\Queries\Graph\Place;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Enum\Graph\RelationReasonEnum;

final class RelatedPlacesQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `Place.related` em lote: vizinhos por `RELATED_TO` (autor, estilo, curadoria; 1 salto) e lugares com
     * um tema em comum (`PART_OF`, 2 saltos). Cada lugar aparece uma vez, com o motivo mais forte
     * (`RELATED_TO` antes de tema), e o texto do motivo sai de `RelationReasonEnum::describe`. Ordem: os da
     * mesma localidade (`LOCATED_IN` a mesma), os de categoria em comum, a melhor nota média (sem nota por
     * último), os com foto e, no empate, o nome.
     *
     * @param list<string> $ids
     * @param int $limit por lugar
     * @return array<string, list<array{place: array<string, mixed>, reason: string}>>
     */
    public function get(array $ids, int $limit): array {
        $rows = $this->graph->read(
            'UNWIND $ids AS id MATCH (s:Place {id: id})
             CALL (s) {
                 MATCH (s)-[r:RELATED_TO]-(p:Place) WHERE p <> s
                 RETURN p, r.kind AS kind, r.value AS value, 0 AS rank
                 UNION
                 MATCH (s)-[:PART_OF]->(t:Theme)<-[:PART_OF]-(p:Place) WHERE p <> s
                 RETURN p, $theme AS kind, t.name AS value, 1 AS rank
             }
             WITH id, s, p, kind, value ORDER BY rank, value
             WITH id, s, p, head(collect({kind: kind, value: value})) AS reason
             WITH id, p, reason,
                  EXISTS { (s)-[:LOCATED_IN]->(:Location)<-[:LOCATED_IN]-(p) } AS same_location,
                  EXISTS { (s)-[:HAS_CATEGORY]->(:Category)<-[:HAS_CATEGORY]-(p) } AS same_category,
                  COLLECT { MATCH (p)<-[v:VISITED]-(:User) WHERE v.rating IS NOT NULL RETURN v.rating } AS ratings
             WITH id, p, reason, same_location, same_category,
                  CASE WHEN size(ratings) = 0 THEN null ELSE reduce(total = 0.0, r IN ratings | total + r) / size(ratings) END AS rating
             ORDER BY same_location DESC, same_category DESC, rating IS NULL, rating DESC, p.image IS NULL, p.name, p.id
             WITH id, collect({place: ' . Cypher::place('p') . ', kind: reason.kind, value: reason.value})[0..$limit] AS related
             RETURN id, related',
            ['ids' => $ids, 'theme' => RelationReasonEnum::THEME->value, 'limit' => $limit]
        );

        $related = [];

        foreach ($rows as $row) {
            $seen = [];

            foreach ($row['related'] as $item) {
                $place_id = $item['place']['id'];

                if (isset($seen[$place_id]) || count($seen) >= $limit) {
                    continue;
                }

                $seen[$place_id] = true;
                $related[$row['id']][] = [
                    'place' => $item['place'],
                    'reason' => RelationReasonEnum::describe($item['kind'], $item['value']),
                ];
            }
        }

        return $related;
    }
}
