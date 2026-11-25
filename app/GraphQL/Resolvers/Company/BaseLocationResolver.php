<?php

namespace App\GraphQL\Resolvers\Company;

use App\Base\GraphQL\BatchLoader;
use App\Queries\Graph\Company\CompanyBaseLocationsQuery;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Company.baseLocation` (lote).
 */
final class BaseLocationResolver {

    public function __construct(private readonly CompanyBaseLocationsQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return Deferred
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): Deferred {
        return BatchLoader::for($info, $args, fn(array $keys) => $this->query->get($keys))->load($root['id'], null);
    }
}
