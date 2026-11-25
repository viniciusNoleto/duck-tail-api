<?php

namespace App\Data\Graph\Place;

use App\Base\GraphQL\ArgsValidator;
use App\Data\Graph\Common\PageDTO;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

class SearchPlacesDTO extends Data {

    public function __construct(
        public ?string $query,
        public string $exact,
        public ?string $category,
        public ?string $category_id,
        public PageDTO $page,
    ) {
    }

    /**
     * `term` vira a consulta do índice de texto completo (`lucene_ranked_query`: palavras escapadas, cada
     * uma como prefixo, com reforço para o nome); `exact` é o termo em minúsculas, para pôr primeiro o nome
     * igual ao digitado. Termo em branco = sem filtro. Categoria (nome) em minúsculas (o grafo guarda assim); `categoryId` é o `Category.id` (UUID).
     *
     * @param array<string, mixed> $args
     * @return SearchPlacesDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): SearchPlacesDTO {
        $validated = ArgsValidator::validate($args, [
            'term' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'categoryId' => 'nullable|string|max:100',
        ], [
            '*' => 'messages.places.invalid_search',
        ]);

        $category = mb_strtolower(trim((string)($validated['category'] ?? '')));
        $category_id = trim((string)($validated['categoryId'] ?? ''));

        $term = trim((string)($validated['term'] ?? ''));

        return new self(
            query: lucene_ranked_query($term),
            exact: mb_strtolower($term),
            category: $category !== '' ? $category : null,
            category_id: $category_id !== '' ? $category_id : null,
            page: PageDTO::fromArgs($args),
        );
    }
}
