<?php

namespace App\Data\Graph\Common;

use App\Base\GraphQL\ArgsValidator;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

/**
 * `limit` das listas sem paginação (`related`, `recommendations`): 1 a 50.
 */
class LimitDTO extends Data {

    public const MAX = 50;

    public function __construct(
        public int $limit,
    ) {
    }

    /**
     * @param mixed $limit
     * @param int $default
     * @return LimitDTO
     * @throws ApiException
     */
    public static function fromArg(mixed $limit, int $default = 10): LimitDTO {
        $validated = ArgsValidator::validate(['limit' => $limit ?? $default], [
            'limit' => 'required|integer|min:1|max:' . self::MAX,
        ], [
            'limit.*' => 'messages.places.invalid_limit',
        ], ['max' => self::MAX]);

        return new self(limit: (int)$validated['limit']);
    }
}
