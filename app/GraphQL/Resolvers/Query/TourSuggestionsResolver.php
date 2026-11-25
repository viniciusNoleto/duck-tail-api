<?php

namespace App\GraphQL\Resolvers\Query;

use App\Data\Graph\Tour\TourSuggestionsDTO;
use App\Queries\Graph\Tour\TourSuggestionsQuery;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `tourSuggestions(placeIds, limit)`: sugestões do buscador de passeios.
 */
final class TourSuggestionsResolver {

    public function __construct(private readonly TourSuggestionsQuery $query) {
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array {
        return $this->query->get(TourSuggestionsDTO::fromArgs($args));
    }
}
