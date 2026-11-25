<?php

namespace App\GraphQL\Resolvers\User;

use App\Base\GraphQL\BatchLoader;
use App\Base\GraphQL\GraphAuth;
use App\Queries\Graph\User\UserItinerariesQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `User.itineraries` (lote): todos para o próprio usuário, só os públicos para os outros.
 */
final class ItinerariesResolver {

    public function __construct(private readonly UserItinerariesQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred {
        $viewer_id = GraphAuth::user($context)?->id;

        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($keys, $viewer_id))->load($root['id'], []);
    }
}
