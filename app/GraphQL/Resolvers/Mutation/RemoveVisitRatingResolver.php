<?php

namespace App\GraphQL\Resolvers\Mutation;

use App\Actions\Graph\Visit\RemoveVisitRatingAction;
use App\Base\GraphQL\GraphAuth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class RemoveVisitRatingResolver {

    public function __construct(private readonly RemoveVisitRatingAction $action) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        $user = GraphAuth::require($context);

        return $this->action->handle((string)$args['placeId'], $user);
    }
}
