<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wikidata (importação de lugares, CC0)
    |--------------------------------------------------------------------------
    |
    | `php artisan ducktail:import --state=MG` consulta o endpoint SPARQL público.
    | A política de uso pede um User-Agent que identifique o cliente.
    |
    */

    'sparql_url' => env('WIKIDATA_SPARQL_URL', 'https://query.wikidata.org/sparql'),

    'user_agent' => env('WIKIDATA_USER_AGENT', 'DuckTailImporter/1.0'),

    'timeout_seconds' => 90,

];
