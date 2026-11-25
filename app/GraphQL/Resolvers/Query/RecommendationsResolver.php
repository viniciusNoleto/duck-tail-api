<?php

namespace App\GraphQL\Resolvers\Query;

use App\Base\GraphQL\GraphAuth;
use App\Data\Graph\Common\LimitDTO;
use App\Queries\Graph\Place\RecommendationsQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `recommendations(limit)`: requer sessão.
 */
final class RecommendationsResolver {

    public function __construct(private readonly RecommendationsQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        $user = GraphAuth::require($context);

        return $this->query->get($user->id, LimitDTO::fromArg($args['limit'] ?? null)->limit);
    }
}
