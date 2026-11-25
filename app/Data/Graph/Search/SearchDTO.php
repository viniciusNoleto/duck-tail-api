<?php

namespace App\Data\Graph\Search;

use App\Base\GraphQL\ArgsValidator;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

class SearchDTO extends Data {

    public const MAX = 20;

    public const PER_KIND = 5;

    public function __construct(
        public ?string $query,
        public string $exact,
        public int $limit,
    ) {
    }

    /**
     * `term` vira a consulta dos índices de texto completo (`lucene_ranked_query`); `exact` é o termo em
     * minúsculas, para pôr primeiro o nome igual ao digitado. Termo em branco = sem resultado. `limit` 1 a 20.
     *
     * @param array<string, mixed> $args
     * @return SearchDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): SearchDTO {
        $validated = ArgsValidator::validate(['limit' => 8, ...$args], [
            'term' => 'nullable|string|max:100',
            'limit' => 'required|integer|min:1|max:' . self::MAX,
        ], [
            'term.*' => 'messages.places.invalid_search',
            'limit.*' => 'messages.places.invalid_limit',
        ], ['max' => self::MAX]);

        $term = trim((string)($validated['term'] ?? ''));

        return new self(
            query: lucene_ranked_query($term),
            exact: mb_strtolower($term),
            limit: (int)$validated['limit'],
        );
    }
}
