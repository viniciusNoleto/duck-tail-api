<?php

namespace App\GraphQL\Resolvers\Query;

use App\Data\Graph\Search\SearchDTO;
use App\Queries\Graph\Search\SearchQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `search(term, limit)`: a busca geral da página inicial (lugares, localidades, categorias, empresas, passeios).
 */
final class SearchResolver {

    public function __construct(private readonly SearchQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        return $this->query->get(SearchDTO::fromArgs($args));
    }
}
