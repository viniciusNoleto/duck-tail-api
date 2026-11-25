<?php

namespace App\GraphQL\Resolvers\Query;

use App\Queries\Graph\Tour\FindTourQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `tour(id)`: null quando não existe.
 */
final class TourResolver {

    public function __construct(private readonly FindTourQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string, mixed>|null
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): ?array {
        return $this->query->get((string)$args['id']);
    }
}
