<?php

namespace App\GraphQL\Resolvers\Place;

use App\Base\GraphQL\BatchLoader;
use App\Queries\Graph\Place\PlaceCompaniesQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Place.companies` (lote).
 */
final class CompaniesResolver {

    public function __construct(private readonly PlaceCompaniesQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred {
        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($keys))->load($root['id'], []);
    }
}
