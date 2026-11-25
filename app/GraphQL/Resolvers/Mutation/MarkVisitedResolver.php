<?php

namespace App\GraphQL\Resolvers\Mutation;

use App\Actions\Graph\Visit\MarkVisitedAction;
use App\Base\GraphQL\GraphAuth;
use App\Data\Graph\Visit\MarkVisitedDTO;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MarkVisitedResolver {

    public function __construct(private readonly MarkVisitedAction $action) {
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

        return $this->action->handle(MarkVisitedDTO::fromArgs($args), $user);
    }
}
