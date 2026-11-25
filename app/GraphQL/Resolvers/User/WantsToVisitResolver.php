<?php

namespace App\GraphQL\Resolvers\User;

use App\Base\GraphQL\BatchLoader;
use App\Enum\Graph\UserPlaceLinkEnum;
use App\Queries\Graph\User\UserPlacesQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `User.wantsToVisit` (lote).
 */
final class WantsToVisitResolver {

    public function __construct(private readonly UserPlacesQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred {
        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($keys, UserPlaceLinkEnum::WANTS_TO_VISIT))->load($root['id'], []);
    }
}
