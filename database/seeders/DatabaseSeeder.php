<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {

    /**
     * Admin (Postgres), grafo de exemplo (Neo4j, com RJ/SP) e, só se pedido, usuários de demonstração.
     */
    public function run(): void {
        $this->call([
            AdminUserSeeder::class,
            GraphSeeder::class,
            RioSaoPauloGraphSeeder::class,
            DemoUsersSeeder::class,
        ]);
    }
}
