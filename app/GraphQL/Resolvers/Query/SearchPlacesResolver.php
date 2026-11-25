<?php

namespace App\GraphQL\Resolvers\Query;

use App\Base\GraphQL\Connection;
use App\Data\Graph\Place\SearchPlacesDTO;
use App\Queries\Graph\Place\SearchPlacesQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `searchPlaces(term, category, first, after)`.
 */
final class SearchPlacesResolver {

    public function __construct(private readonly SearchPlacesQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        $dto = SearchPlacesDTO::fromArgs($args);

        return Connection::fromSlice($this->query->get($dto), $dto->page);
    }
}
