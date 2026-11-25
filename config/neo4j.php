<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Neo4j (grafo)
    |--------------------------------------------------------------------------
    |
    | Lugares, cidades, categorias, temas, empresas, visitas e roteiros ficam no
    | Neo4j; usuários e autenticação continuam no Postgres. O cliente
    | (`laudis/neo4j-php-client`, protocolo Bolt) é registrado no
    | `AppServiceProvider` e usado só através de `App\Base\Graph\Graph`.
    |
    */

    'uri' => env('NEO4J_URI', 'bolt://neo4j:7687'),

    'username' => env('NEO4J_USERNAME', 'neo4j'),

    'password' => env('NEO4J_PASSWORD', ''),

    // Quantas vezes `Graph::transaction()` repete o callback em erro transitório do Neo4j (deadlock entre
    // transações de escrita); regra de negócio e erro de sintaxe nunca são repetidos.
    'transaction_attempts' => (int)env('NEO4J_TRANSACTION_ATTEMPTS', 3),

    // Grafo dos testes (container `neo4j-testing`): `tests/TestCase.php` troca `uri` por este valor e
    // recusa rodar se ele estiver vazio ou igual ao de desenvolvimento.
    'testing_uri' => env('NEO4J_TESTING_URI'),

];
