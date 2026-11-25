<?php

namespace App\Data\Graph\Location;

use App\Base\GraphQL\ArgsValidator;
use App\Data\Graph\Common\PageDTO;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

/**
 * `Location.places(term, categoryId, first, after)`: a busca dentro da localidade.
 */
class LocationPlacesDTO extends Data {

    public function __construct(
        public ?string $query,
        public string $exact,
        public ?string $category_id,
        public PageDTO $page,
    ) {
    }

    /**
     * `term` vira a consulta do índice de texto completo (`lucene_ranked_query`), `exact` o termo em
     * minúsculas (nome igual primeiro); termo em branco = sem filtro. `categoryId` é o `Category.id`.
     *
     * @param array<string, mixed> $args
     * @return LocationPlacesDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): LocationPlacesDTO {
        $validated = ArgsValidator::validate($args, [
            'term' => 'nullable|string|max:100',
            'categoryId' => 'nullable|string|max:100',
        ], [
            '*' => 'messages.places.invalid_search',
        ]);

        $term = trim((string)($validated['term'] ?? ''));
        $category_id = trim((string)($validated['categoryId'] ?? ''));

        return new self(
            query: lucene_ranked_query($term),
            exact: mb_strtolower($term),
            category_id: $category_id !== '' ? $category_id : null,
            page: PageDTO::fromArgs($args),
        );
    }
}
