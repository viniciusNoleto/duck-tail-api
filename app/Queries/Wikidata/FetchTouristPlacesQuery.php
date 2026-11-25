<?php

namespace App\Queries\Wikidata;

use App\Data\Graph\Import\ImportedPlaceDTO;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Lugares turísticos de uma subdivisão de país no Wikidata (CC0), via SPARQL: atrações turísticas
 * (P31/P279* Q570116) **ou** bens tombados (qualquer P1435) com coordenada (P625), dentro de uma cidade
 * (município, cidade, vila: P31/P279? de Q3184121, Q15284, Q515, Q3957, Q532) subordinada diretamente (P131) à
 * subdivisão — identificada pelo código ISO 3166-2 (P300: `BR-MG`, `PT-11`, `AR-C`). Traz o país (P17)
 * da subdivisão, imagem (P18), tipos (P31 → categorias), autores (P84) e estilos (P149), com rótulos em
 * português (inglês quando não há). As categorias vêm com o nome nos três idiomas da API: pt-br (o
 * rótulo `pt-br`; sem ele, o `pt` com a acentuação do Brasil), es (`es`) e en (`en`).
 *
 * Só bens tombados entram além das atrações porque a subárvore de Q570116 sozinha deixava de fora
 * quase todas as igrejas históricas (o caso de uso do produto: Aleijadinho em Ouro Preto e Congonhas).
 */
final class FetchTouristPlacesQuery {

    /**
     * @param string $area código ISO 3166-2 (`BR-MG`)
     * @param int $limit
     * @return list<ImportedPlaceDTO> um por lugar (linhas repetidas do SPARQL ficam com a primeira)
     * @throws RequestException
     */
    public function get(string $area, int $limit): array {
        $response = Http::withHeaders(['User-Agent' => config('wikidata.user_agent'), 'Accept' => 'application/sparql-results+json'])
            ->timeout(config('wikidata.timeout_seconds'))
            ->retry(2, 2000)
            ->asForm()
            ->post(config('wikidata.sparql_url'), ['query' => self::sparql($area, $limit)])
            ->throw();

        $places = [];

        foreach ($response->json('results.bindings', []) as $binding) {
            $place = self::toDTO($binding);

            if ($place && !isset($places[$place->wikidata_id])) {
                $places[$place->wikidata_id] = $place;
            }
        }

        return array_values($places);
    }

    /**
     * @param string $area
     * @param int $limit
     * @return string
     */
    public static function sparql(string $area, int $limit): string {
        $iso = strtoupper($area);

        return <<<SPARQL
            SELECT ?place ?placeLabel ?placeDescription ?coord ?image ?city ?cityLabel ?cityCoord
                   ?state ?stateLabel ?country ?countryLabel
                   (GROUP_CONCAT(DISTINCT CONCAT(COALESCE(?typePtBr, ""), "~", COALESCE(?typePt, ""), "~", COALESCE(?typeEs, ""), "~", COALESCE(?typeEn, "")); separator="|") AS ?types)
                   (GROUP_CONCAT(DISTINCT ?architectLabel; separator="|") AS ?authors)
                   (GROUP_CONCAT(DISTINCT ?styleLabel; separator="|") AS ?styles)
            WHERE {
              ?state wdt:P300 "{$iso}" .
              OPTIONAL { ?state wdt:P17 ?country . }
              VALUES ?cityClass { wd:Q3184121 wd:Q15284 wd:Q515 wd:Q3957 wd:Q532 }
              ?city wdt:P31/wdt:P279? ?cityClass ; wdt:P131 ?state .
              { ?place wdt:P31/wdt:P279* wd:Q570116 . } UNION { ?place wdt:P1435 ?heritage . }
              ?place wdt:P131+ ?city ;
                     wdt:P625 ?coord .
              OPTIONAL { ?city wdt:P625 ?cityCoord . }
              OPTIONAL { ?place wdt:P18 ?image . }
              OPTIONAL {
                ?place wdt:P31 ?type .
                OPTIONAL { ?type rdfs:label ?typePtBr . FILTER(LANG(?typePtBr) = "pt-br") }
                OPTIONAL { ?type rdfs:label ?typePt . FILTER(LANG(?typePt) = "pt") }
                OPTIONAL { ?type rdfs:label ?typeEs . FILTER(LANG(?typeEs) = "es") }
                OPTIONAL { ?type rdfs:label ?typeEn . FILTER(LANG(?typeEn) = "en") }
              }
              OPTIONAL { ?place wdt:P84 ?architect . ?architect rdfs:label ?architectLabel . FILTER(LANG(?architectLabel) = "pt") }
              OPTIONAL { ?place wdt:P149 ?style . ?style rdfs:label ?styleLabel . FILTER(LANG(?styleLabel) = "pt") }
              SERVICE wikibase:label {
                bd:serviceParam wikibase:language "pt-br,pt,en" .
                ?place rdfs:label ?placeLabel . ?place schema:description ?placeDescription . ?city rdfs:label ?cityLabel .
                ?state rdfs:label ?stateLabel . ?country rdfs:label ?countryLabel .
              }
            }
            GROUP BY ?place ?placeLabel ?placeDescription ?coord ?image ?city ?cityLabel ?cityCoord ?state ?stateLabel ?country ?countryLabel
            LIMIT {$limit}
            SPARQL;
    }

    /**
     * Uma linha do SPARQL → DTO. Sem coordenada válida, sem QID (do lugar, da cidade ou da subdivisão) ou sem nome (rótulo que é o próprio QID:
     * o item não tem nome em pt nem en) → `null` (a linha é pulada).
     *
     * @param array<string, array{value: string}> $binding
     * @return ImportedPlaceDTO|null
     */
    public static function toDTO(array $binding): ?ImportedPlaceDTO {
        $value = fn(string $key): ?string => isset($binding[$key]['value']) && trim($binding[$key]['value']) !== '' ? trim($binding[$key]['value']) : null;
        $list = fn(string $key): array => array_values(array_unique(array_filter(array_map('trim', explode('|', (string)$value($key))))));

        $id = self::qid($value('place'));
        $city_id = self::qid($value('city'));
        $state_id = self::qid($value('state'));
        $coordinates = self::point($value('coord'));
        $name = $value('placeLabel');

        if (!$id || !$city_id || !$state_id || !$coordinates || !$name || $name === $id) {
            return null;
        }

        $city_coordinates = self::point($value('cityCoord'));
        $image = $value('image');
        $country_id = self::qid($value('country'));

        return new ImportedPlaceDTO(
            wikidata_id: $id,
            name: $name,
            description: $value('placeDescription'),
            latitude: $coordinates[0],
            longitude: $coordinates[1],
            image: $image ? preg_replace('#^http://#', 'https://', $image) : null,
            city_wikidata_id: $city_id,
            city_name: $value('cityLabel') ?? $city_id,
            city_latitude: $city_coordinates[0] ?? null,
            city_longitude: $city_coordinates[1] ?? null,
            state_wikidata_id: $state_id,
            state_name: $value('stateLabel') ?? $state_id,
            country_wikidata_id: $country_id,
            country_name: $country_id ? ($value('countryLabel') ?? $country_id) : null,
            categories: self::categories($list('types')),
            authors: $list('authors'),
            styles: $list('styles'),
        );
    }

    /**
     * Tipos do SPARQL (`pt-br~pt~es~en`, um por tipo; o formato antigo, só o nome, também vale) →
     * categorias com o nome em cada idioma, em minúsculas. Sem nome em português (nem `pt-br` nem `pt`),
     * o tipo fica de fora; `es`/`en` ausentes ficam `null` (a leitura cai no pt-br). Dois tipos com o
     * mesmo nome viram uma categoria só (a categoria é única pelo nome).
     *
     * @param list<string> $types
     * @return list<array{name: string, name_es: string|null, name_en: string|null}>
     */
    public static function categories(array $types): array {
        $categories = [];

        foreach ($types as $type) {
            $labels = array_map(fn(string $label) => trim($label) === '' ? null : mb_strtolower(trim($label)), explode('~', $type));
            [$pt_br, $pt, $es, $en] = array_pad($labels, 4, null);
            $name = $pt_br ?? ($pt === null ? null : self::brazilianSpelling($pt));

            if ($name !== null && !isset($categories[$name])) {
                $categories[$name] = ['name' => $name, 'name_es' => $es, 'name_en' => $en];
            }
        }

        return array_values($categories);
    }

    /**
     * Acentuação do português do Brasil num rótulo `pt` (europeu): antes de `m`/`n` seguido de vogal, o
     * agudo vira circunflexo (`património` → `patrimônio`, `arquitetónica` → `arquitetônica`). Só vale para
     * o rótulo de tipo quando o Wikidata não tem o `pt-br`.
     *
     * @param string $label
     * @return string
     */
    public static function brazilianSpelling(string $label): string {
        return (string)preg_replace_callback('/([éó])(?=[mn][aeiouáéíóú])/u', fn(array $match) => $match[1] === 'é' ? 'ê' : 'ô', $label);
    }

    /**
     * `http://www.wikidata.org/entity/Q123` → `Q123`.
     *
     * @param string|null $uri
     * @return string|null
     */
    private static function qid(?string $uri): ?string {
        return $uri && preg_match('#/(Q\d+)$#', $uri, $match) ? $match[1] : null;
    }

    /**
     * WKT `Point(longitude latitude)` → `[latitude, longitude]`.
     *
     * @param string|null $wkt
     * @return array{0: float, 1: float}|null
     */
    private static function point(?string $wkt): ?array {
        if (!$wkt || !preg_match('/^Point\((-?[\d.]+) (-?[\d.]+)\)$/', $wkt, $match)) {
            return null;
        }

        [$longitude, $latitude] = [(float)$match[1], (float)$match[2]];

        return abs($latitude) <= 90 && abs($longitude) <= 180 ? [$latitude, $longitude] : null;
    }
}
