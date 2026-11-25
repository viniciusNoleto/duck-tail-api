<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase {

    /**
     * Os testes nunca tocam os dados de desenvolvimento:
     * - Postgres: sempre `${DB_DATABASE}_testing` (criado pelo init do Postgres e por
     *   `make recreate-testing-database`), com o nome derivado do mesmo `.env`;
     * - Neo4j: sempre `NEO4J_TESTING_URI` (container `neo4j-testing`). Os testes de Feature **apagam o grafo**
     *   antes de cada teste, então sem essa variável, ou com ela igual a `NEO4J_URI`, a suíte nem começa.
     *
     * @return Application
     */
    public function createApplication() {
        $app = parent::createApplication();
        $config = $app['config'];

        $connection = $config->get('database.default');
        $database = (string)$config->get("database.connections.{$connection}.database");

        if (!str_ends_with($database, '_testing')) {
            $config->set("database.connections.{$connection}.database", $database . '_testing');
        }

        $testing_uri = (string)$config->get('neo4j.testing_uri');

        if ($testing_uri === '' || $testing_uri === $config->get('neo4j.uri')) {
            throw new RuntimeException('Defina NEO4J_TESTING_URI (Neo4j só dos testes, diferente de NEO4J_URI): os testes apagam o grafo.');
        }

        $config->set('neo4j.uri', $testing_uri);

        return $app;
    }
}
