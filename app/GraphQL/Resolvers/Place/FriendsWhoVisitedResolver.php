<?php

namespace App\GraphQL\Resolvers\Place;

use App\Base\GraphQL\BatchLoader;
use App\Base\GraphQL\GraphAuth;
use App\Queries\Graph\Place\FriendsWhoVisitedQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Place.friendsWhoVisited` (lote). Sem sessão não há "amigos": lista vazia, sem erro (a página do lugar
 * é pública).
 */
final class FriendsWhoVisitedResolver {

    public function __construct(private readonly FriendsWhoVisitedQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred|array{}
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred|array {
        $user = GraphAuth::user($context);

        if (!$user) {
            return [];
        }

        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($user->id, $keys))->load($root['id'], []);
    }
}
