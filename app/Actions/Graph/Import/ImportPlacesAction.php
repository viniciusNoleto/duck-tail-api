<?php

namespace App\Actions\Graph\Import;

use App\Base\Graph\Graph;
use App\Data\Graph\Import\ImportedPlaceDTO;
use App\Enum\Graph\LocationKindEnum;

final class ImportPlacesAction {

    public const BATCH_SIZE = 200;

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Grava lugares do Wikidata: `MERGE` por `wikidataId` (rodar de novo atualiza, não duplica), a cadeia
     * de localidades (país ← estado ← cidade, por `WITHIN`, também por `wikidataId`), o lugar na cidade
     * (`LOCATED_IN`), categorias (`HAS_CATEGORY`, com o nome em pt-br, es e en; as que o lugar deixou de ter
     * saem, e categoria sem nenhum lugar é apagada), autores e estilos (usados pelo `ducktail:relate`). O `id`
     * público de lugar e localidade importados é o próprio QID (`Q123`). Em lotes de 200, cada lote numa
     * transação.
     *
     * @param list<ImportedPlaceDTO> $places
     * @return int quantos lugares foram gravados
     */
    public function handle(array $places): int {
        $written = 0;

        foreach (array_chunk($places, self::BATCH_SIZE) as $batch) {
            $rows = $this->graph->write(
                'UNWIND $places AS row
                 MERGE (s:Location {wikidataId: row.state_wikidata_id})
                   ON CREATE SET s.id = row.state_wikidata_id
                 SET s.name = row.state_name, s.kind = $state
                 WITH s, row
                 CALL (s, row) {
                     WITH s, row WHERE row.country_wikidata_id IS NOT NULL
                     MERGE (country:Location {wikidataId: row.country_wikidata_id})
                       ON CREATE SET country.id = row.country_wikidata_id
                     SET country.name = row.country_name, country.kind = $country
                     MERGE (s)-[:WITHIN]->(country)
                 }
                 MERGE (c:Location {wikidataId: row.city_wikidata_id})
                   ON CREATE SET c.id = row.city_wikidata_id
                 SET c.name = row.city_name, c.kind = $city,
                     c.location = CASE WHEN row.city_latitude IS NULL THEN c.location
                                       ELSE point({latitude: row.city_latitude, longitude: row.city_longitude}) END
                 MERGE (c)-[:WITHIN]->(s)
                 MERGE (p:Place {wikidataId: row.wikidata_id})
                   ON CREATE SET p.id = row.wikidata_id
                 SET p.name = row.name, p.description = row.description, p.image = row.image,
                     p.location = point({latitude: row.latitude, longitude: row.longitude}),
                     p.authors = row.authors, p.styles = row.styles
                 WITH p, c, row
                 OPTIONAL MATCH (p)-[old:LOCATED_IN]->(other:Location) WHERE other <> c
                 DELETE old
                 MERGE (p)-[:LOCATED_IN]->(c)
                 WITH p, row
                 CALL (p, row) {
                     OPTIONAL MATCH (p)-[stale:HAS_CATEGORY]->(k:Category)
                     WHERE NOT k.name IN [category IN row.categories | category.name]
                     DELETE stale
                 }
                 CALL (p, row) {
                     UNWIND row.categories AS category
                     MERGE (k:Category {name: category.name}) ON CREATE SET k.id = randomUUID()
                     SET k.nameEsMx = coalesce(category.name_es, k.nameEsMx), k.nameEnUs = coalesce(category.name_en, k.nameEnUs)
                     MERGE (p)-[:HAS_CATEGORY]->(k)
                 }
                 RETURN count(p) AS written',
                [
                    'places' => array_map(fn(ImportedPlaceDTO $place) => $place->toArray(), $batch),
                    'country' => LocationKindEnum::COUNTRY->value,
                    'state' => LocationKindEnum::STATE->value,
                    'city' => LocationKindEnum::CITY->value,
                ]
            );

            $written += (int)($rows[0]['written'] ?? 0);
        }

        // Categoria que ficou sem lugar (o nome mudou: `património` → `patrimônio`) sai do grafo.
        $this->graph->write('MATCH (k:Category) WHERE NOT EXISTS { (k)<-[:HAS_CATEGORY]-(:Place) } DETACH DELETE k');

        return $written;
    }
}
