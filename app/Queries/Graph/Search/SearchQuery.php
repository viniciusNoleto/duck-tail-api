<?php

namespace App\Queries\Graph\Search;

use App\Base\Graph\Graph;
use App\Data\Graph\Search\SearchDTO;

final class SearchQuery {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Busca geral: lugares (`place_search`, nome e descrição) e localidades, categorias, empresas e passeios
     * (`catalog_search`, só o nome), no máximo `PER_KIND` de cada tipo. Ordem: nome igual ao termo, nome que
     * começa com o termo, depois a relevância do índice (que pesa mais o nome; e a faixa vale já dentro de
     * cada tipo, antes do corte de `PER_KIND`); em cada faixa, categoria e localidade antes dos
     * lugares (são os atalhos de quem digita "museu" ou "Ouro Preto"). Cada item vem com o contexto que o
     * distingue: a cidade do lugar, a localidade de cima, a base da empresa, a empresa do passeio.
     *
     * @param SearchDTO $dto
     * @return list<array<string, mixed>>
     */
    public function get(SearchDTO $dto): array {
        if ($dto->query === null) {
            return [];
        }

        $rows = $this->graph->read(
            'CALL () {
                 CALL db.index.fulltext.queryNodes(\'place_search\', $query) YIELD node, score
                 WITH node, score ORDER BY CASE WHEN toLower(node.name) = $exact THEN 2 WHEN toLower(node.name) STARTS WITH $exact THEN 1 ELSE 0 END DESC, score DESC LIMIT $perKind
                 RETURN node, score, \'place\' AS kind
               UNION ALL
                 CALL db.index.fulltext.queryNodes(\'catalog_search\', $query) YIELD node, score
                 WITH node, score, CASE WHEN node:Location THEN \'location\' WHEN node:Category THEN \'category\'
                                        WHEN node:Company THEN \'company\' ELSE \'tour\' END AS kind
                 WHERE kind <> \'category\' OR EXISTS { (node)<-[:HAS_CATEGORY]-(:Place) }
                 WITH kind, node, score ORDER BY CASE WHEN toLower(node.name) = $exact THEN 2 WHEN toLower(node.name) STARTS WITH $exact THEN 1 ELSE 0 END DESC, score DESC
                 WITH kind, collect({node: node, score: score})[0..$perKind] AS hits
                 UNWIND hits AS hit
                 RETURN hit.node AS node, hit.score AS score, kind
             }
             WITH node, kind, score,
                  CASE WHEN toLower(node.name) = $exact THEN 2 WHEN toLower(node.name) STARTS WITH $exact THEN 1 ELSE 0 END AS band,
                  CASE kind WHEN \'category\' THEN 0 WHEN \'location\' THEN 1 ELSE 2 END AS kindOrder
             ORDER BY band DESC, kindOrder, score DESC, node.name
             LIMIT $limit
             RETURN {
                 kind: kind,
                 id: coalesce(node.id, node.name),
                 name: node.name,
                 names: CASE kind WHEN \'category\' THEN {ptBr: node.name, esMx: coalesce(node.nameEsMx, node.name), enUs: coalesce(node.nameEnUs, node.name)} ELSE null END,
                 subtype: CASE kind WHEN \'location\' THEN node.kind WHEN \'company\' THEN node.kind ELSE null END,
                 context: CASE kind
                     WHEN \'place\' THEN head([(node)-[:LOCATED_IN]->(l:Location) | l.name])
                     WHEN \'location\' THEN head([(node)-[:WITHIN]->(l:Location) | l.name])
                     WHEN \'company\' THEN head([(node)-[:BASED_IN]->(l:Location) | l.name])
                     WHEN \'tour\' THEN head([(node)<-[:OFFERS]-(co:Company) | co.name])
                     ELSE null END,
                 count: CASE kind WHEN \'category\' THEN COUNT { (node)<-[:HAS_CATEGORY]-(:Place) } ELSE null END,
                 image: CASE kind WHEN \'place\' THEN node.image ELSE null END
             } AS result',
            ['query' => $dto->query, 'exact' => $dto->exact, 'limit' => $dto->limit, 'perKind' => SearchDTO::PER_KIND]
        );

        return array_column($rows, 'result');
    }
}
