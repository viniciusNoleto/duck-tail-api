<?php

namespace App\Actions\Graph\Follow;

use App\Base\Graph\Cypher;
use App\Base\Graph\Graph;
use App\Exceptions\ApiException;
use App\Models\User;

final class FollowUserAction {

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * `(User)-[:FOLLOWS]->(User)`, idempotente. O seguido precisa existir no Postgres (a fonte dos
     * usuários): id inválido ou inexistente → 404; seguir a si mesmo → 422.
     *
     * @param mixed $user_id id do seguido (o `ID` do GraphQL)
     * @param User $follower
     * @return array{id: string, name: string} o seguido
     * @throws ApiException
     */
    public function handle(mixed $user_id, User $follower): array {
        $parsed_id = parse_id($user_id);
        $followed = $parsed_id ? User::query()->find($parsed_id) : null;

        if (!$followed) {
            throw new ApiException('messages.users.not_found', 404);
        }

        if ($followed->id === $follower->id) {
            throw new ApiException('messages.follows.cannot_follow_self', 422);
        }

        $rows = $this->graph->write(
            'MERGE (a:User {id: $follower_id}) ON CREATE SET a.name = $follower_name
             MERGE (b:User {id: $followed_id}) SET b.name = $followed_name
             MERGE (a)-[f:FOLLOWS]->(b) ON CREATE SET f.since = date($today)
             RETURN ' . Cypher::user('b') . ' AS user',
            [
                'follower_id' => $follower->id,
                'follower_name' => $follower->name,
                'followed_id' => $followed->id,
                'followed_name' => $followed->name,
                'today' => business_today(),
            ]
        );

        return $rows[0]['user'];
    }
}
