<?php

namespace App\GraphQL\Resolvers\Company;

use App\Base\GraphQL\BatchLoader;
use App\Queries\Graph\Company\CompanyPlacesQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Company.places` (lote).
 */
final class PlacesResolver {

    public function __construct(private readonly CompanyPlacesQuery $query) {
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
