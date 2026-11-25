<?php

namespace App\GraphQL\Resolvers\Query;

use App\Base\GraphQL\GraphAuth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `me`: o usuário da sessão (do Postgres, a fonte), ou null. Os campos de lista (`visited`…) vêm do grafo
 * pelo mesmo id.
 */
final class MeResolver {

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array{id: string, name: string}|null
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): ?array {
        $user = GraphAuth::user($context);

        return $user ? ['id' => (string)$user->id, 'name' => $user->name] : null;
    }
}
