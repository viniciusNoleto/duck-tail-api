<?php

namespace App\Data\Graph\Common;

use App\Base\GraphQL\ArgsValidator;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

/**
 * Paginação por cursor das conexões (`first`, `after`): `offset` é quantos itens já foram entregues.
 */
class PageDTO extends Data {

    public const DEFAULT_FIRST = 20;

    public const MAX_FIRST = 50;

    public function __construct(
        public int $first,
        public int $offset,
    ) {
    }

    /**
     * `first` fora de 1..50 e cursor que não veio da API → 422 (nada de "corrigir" em silêncio).
     *
     * @param array<string, mixed> $args
     * @return PageDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): PageDTO {
        $validated = ArgsValidator::validate($args, [
            'first' => 'nullable|integer|min:1|max:' . self::MAX_FIRST,
            'after' => 'nullable|string|max:64',
        ], [
            'first.*' => 'messages.places.invalid_page_size',
            'after.*' => 'messages.places.invalid_cursor',
        ], ['max' => self::MAX_FIRST]);

        $offset = decode_cursor($validated['after'] ?? null);

        if ($offset === false) {
            throw new ApiException('messages.places.invalid_cursor', 422);
        }

        return new self(first: (int)($validated['first'] ?? self::DEFAULT_FIRST), offset: $offset ?? 0);
    }
}
