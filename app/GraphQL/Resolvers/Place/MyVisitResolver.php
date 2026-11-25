<?php

namespace App\GraphQL\Resolvers\Place;

use App\Base\GraphQL\BatchLoader;
use App\Base\GraphQL\GraphAuth;
use App\Queries\Graph\Place\MyVisitsQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Place.myVisit` (lote). Sem sessão, `null` sem erro (a página do lugar é pública).
 */
final class MyVisitResolver {

    public function __construct(private readonly MyVisitsQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred|null
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): ?Deferred {
        $user = GraphAuth::user($context);

        if (!$user) {
            return null;
        }

        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($user->id, $keys))->load($root['id'], null);
    }
}
