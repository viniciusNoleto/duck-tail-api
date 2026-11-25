<?php

namespace App\Base\Graph;

/**
 * Constraints e índices do grafo — o equivalente às migrations do Postgres para o Neo4j. Todos com
 * `IF NOT EXISTS`: aplicar de novo (`php artisan ducktail:graph-migrate`, rodado pelo deploy) é seguro.
 *
 * Política igual à das migrations (expand/contract): só **acrescente** itens aqui. Remover ou renomear
 * um índice/constraint é um `DROP … IF EXISTS` explícito numa release posterior, quando nenhum código
 * em produção depender dele.
 */
final class GraphSchema {

    /**
     * Unicidade (cria também o índice de busca pela propriedade). A unicidade do Neo4j ignora nós sem a
     * propriedade: lugar de exemplo sem `wikidataId` não conflita com outro.
     */
    public const CONSTRAINTS = [
        'place_id_unique' => 'FOR (n:Place) REQUIRE n.id IS UNIQUE',
        'place_wikidata_id_unique' => 'FOR (n:Place) REQUIRE n.wikidataId IS UNIQUE',
        'location_id_unique' => 'FOR (n:Location) REQUIRE n.id IS UNIQUE',
        'location_wikidata_id_unique' => 'FOR (n:Location) REQUIRE n.wikidataId IS UNIQUE',
        'category_name_unique' => 'FOR (n:Category) REQUIRE n.name IS UNIQUE',
        'category_id_unique' => 'FOR (n:Category) REQUIRE n.id IS UNIQUE',
        'theme_name_unique' => 'FOR (n:Theme) REQUIRE n.name IS UNIQUE',
        'company_id_unique' => 'FOR (n:Company) REQUIRE n.id IS UNIQUE',
        'tour_id_unique' => 'FOR (n:Tour) REQUIRE n.id IS UNIQUE',
        'user_id_unique' => 'FOR (n:User) REQUIRE n.id IS UNIQUE',
        'itinerary_id_unique' => 'FOR (n:Itinerary) REQUIRE n.id IS UNIQUE',
    ];

    /**
     * Índices: espacial (`point.distance` usa o índice de ponto) e texto completo com `standard-folding`
     * (busca "sao joao" encontra "São João"): `place_search` para lugares e `catalog_search` (só o nome)
     * para a busca geral (`search`) em localidades, categorias, empresas e passeios.
     */
    public const INDEXES = [
        'place_location' => 'CREATE POINT INDEX place_location IF NOT EXISTS FOR (n:Place) ON (n.location)',
        'location_kind' => 'CREATE INDEX location_kind IF NOT EXISTS FOR (n:Location) ON (n.kind)',
        'place_name' => 'CREATE INDEX place_name IF NOT EXISTS FOR (n:Place) ON (n.name)',
        'place_search' => "CREATE FULLTEXT INDEX place_search IF NOT EXISTS FOR (n:Place) ON EACH [n.name, n.description] OPTIONS {indexConfig: {`fulltext.analyzer`: 'standard-folding'}}",
        'catalog_search' => "CREATE FULLTEXT INDEX catalog_search IF NOT EXISTS FOR (n:Location|Category|Company|Tour) ON EACH [n.name] OPTIONS {indexConfig: {`fulltext.analyzer`: 'standard-folding'}}",
    ];

    /**
     * Todos os comandos, constraints primeiro.
     *
     * @return list<string>
     */
    public static function statements(): array {
        $statements = [];

        foreach (self::CONSTRAINTS as $name => $definition) {
            $statements[] = "CREATE CONSTRAINT {$name} IF NOT EXISTS {$definition}";
        }

        return [...$statements, ...array_values(self::INDEXES)];
    }
}
