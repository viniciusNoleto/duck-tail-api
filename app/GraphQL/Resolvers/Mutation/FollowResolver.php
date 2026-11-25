<?php

namespace App\GraphQL\Resolvers\Mutation;

use App\Actions\Graph\Follow\FollowUserAction;
use App\Base\GraphQL\GraphAuth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class FollowResolver {

    public function __construct(private readonly FollowUserAction $action) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array{id: string, name: string}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        $user = GraphAuth::require($context);

        return $this->action->handle($args['userId'], $user);
    }
}
