<?php

namespace Database\Seeders;

use App\Base\Graph\Graph;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuários de demonstração (só com `SEED_DEMO_USERS=true`, nunca em produção): Ana e Bruno, que se seguem
 * e já visitaram lugares do `GraphSeeder`, para o web mostrar "amigos que já foram" e recomendações.
 * Senha: `SEED_DEMO_PASSWORD`.
 */
class DemoUsersSeeder extends Seeder {

    public const USERS = [
        ['name' => 'Ana Demo', 'email' => 'ana@ducktail.local', 'visited' => [
            ['seed-igreja-sao-francisco-ouro-preto', 5], ['seed-santuario-bom-jesus-congonhas', 5], ['seed-mina-passagem', 4],
        ], 'wants' => ['seed-matriz-santo-antonio-tiradentes']],
        ['name' => 'Bruno Demo', 'email' => 'bruno@ducktail.local', 'visited' => [
            ['seed-igreja-sao-francisco-ouro-preto', 4], ['seed-matriz-santo-antonio-tiradentes', 5], ['seed-estacao-maria-fumaca', 5],
        ], 'wants' => []],
    ];

    public function run(Graph $graph): void {
        $seed = config('seed.demo');

        if (!$seed['enabled'] || app()->isProduction() || !$seed['password']) {
            $this->command?->info('SEED_DEMO_USERS desligado (ou sem SEED_DEMO_PASSWORD): sem usuários de demonstração.');

            return;
        }

        $ids = [];

        foreach (self::USERS as $demo) {
            $user = User::query()->firstOrCreate(['email' => $demo['email']], ['name' => $demo['name'], 'password' => $seed['password'], 'is_admin' => false]);
            $ids[] = $user->id;

            $graph->write(
                'MERGE (u:User {id: $id}) SET u.name = $name
                 WITH u
                 UNWIND $visited AS visit
                 MATCH (p:Place {id: visit[0]})
                 MERGE (u)-[v:VISITED]->(p) SET v.date = date($today), v.rating = visit[1]',
                ['id' => $user->id, 'name' => $user->name, 'visited' => array_map(fn(array $visit) => [seed_id($visit[0]), $visit[1]], $demo['visited']), 'today' => business_today()]
            );
            $graph->write(
                'MATCH (u:User {id: $id}) UNWIND $wants AS place_id MATCH (p:Place {id: place_id})
                 MERGE (u)-[w:WANTS_TO_VISIT]->(p) ON CREATE SET w.since = date($today)',
                ['id' => $user->id, 'wants' => array_map(seed_id(...), $demo['wants']), 'today' => business_today()]
            );
        }

        $graph->write(
            'MATCH (a:User {id: $a}), (b:User {id: $b}) MERGE (a)-[:FOLLOWS]->(b) MERGE (b)-[:FOLLOWS]->(a)',
            ['a' => $ids[0], 'b' => $ids[1]]
        );

        $this->command?->info('Usuários de demonstração: ' . implode(', ', array_column(self::USERS, 'email')) . '.');
    }
}
