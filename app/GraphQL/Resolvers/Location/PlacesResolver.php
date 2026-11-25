<?php

namespace App\GraphQL\Resolvers\Location;

use App\Base\GraphQL\Connection;
use App\Data\Graph\Location\LocationPlacesDTO;
use App\Queries\Graph\Location\LocationPlacesQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Location.places(term, categoryId, first, after)`: busca dentro da localidade, paginação por cursor. Uma consulta por localidade (a página costuma
 * mostrar uma só; paginar em lote complicaria o SKIP/LIMIT por pai sem ganho real).
 */
final class PlacesResolver {

    public function __construct(private readonly LocationPlacesQuery $query) {
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string, mixed>
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        $dto = LocationPlacesDTO::fromArgs($args);

        return Connection::fromSlice($this->query->get($root['id'], $dto), $dto->page);
    }
}
