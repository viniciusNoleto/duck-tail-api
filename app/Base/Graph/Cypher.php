<?php

namespace App\Base\Graph;

/**
 * Projeções Cypher reutilizadas: o formato de cada tipo do schema GraphQL sai de um lugar só
 * (as Queries e as Actions interpolam estes trechos, nunca dados do usuário — esses vão como parâmetro).
 */
final class Cypher {

    /**
     * `Place` sem os campos que têm resolver próprio (city, categories…).
     *
     * @param string $var
     * @return string
     */
    public static function place(string $var = 'p'): string {
        return "{$var} {.id, .name, .description, .image, latitude: {$var}.location.latitude, longitude: {$var}.location.longitude}";
    }

    /**
     * @param string $var
     * @return string
     */
    public static function location(string $var = 'l'): string {
        return "{$var} {.id, .name, .kind, latitude: {$var}.location.latitude, longitude: {$var}.location.longitude}";
    }

    /**
     * `Category`: o id é um UUID (criado no `MERGE` ou por `AssignCategoryIdsAction`); categoria que
     * ainda não tem id usa o nome, que também é único (e `FindCategoryQuery` encontra pelos dois).
     * `names`: o nome em cada idioma da API; `es`/`en` que faltam caem no pt-br (`name`).
     *
     * @param string $var
     * @return string
     */
    public static function category(string $var = 'c'): string {
        return "{$var} {id: coalesce({$var}.id, {$var}.name), .name, names: {ptBr: {$var}.name, esMx: coalesce({$var}.nameEsMx, {$var}.name), enUs: coalesce({$var}.nameEnUs, {$var}.name)}}";
    }

    /**
     * @param string $var
     * @return string
     */
    public static function company(string $var = 'co'): string {
        return "{$var} {.id, .name, .description, .kind, .website, .phone}";
    }

    /**
     * @param string $var
     * @return string
     */
    public static function tour(string $var = 't'): string {
        return "{$var} {.id, .name, .description, .durationMinutes, .price, .currency}";
    }

    /**
     * `User` do grafo: o id é o inteiro do Postgres, devolvido como string (tipo `ID`).
     *
     * @param string $var
     * @return string
     */
    public static function user(string $var = 'u'): string {
        return "{$var} {id: toString({$var}.id), .name}";
    }

    /**
     * @param string $var
     * @return string
     */
    public static function itinerary(string $var = 'i'): string {
        return "{$var} {.id, .title, .isPublic}";
    }
}
