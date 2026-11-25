<?php

namespace App\Data\Graph\Location;

use App\Base\GraphQL\ArgsValidator;
use App\Data\Graph\Common\LimitDTO;
use App\Enum\Graph\LocationKindEnum;
use App\Exceptions\ApiException;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class ListLocationsDTO extends Data {

    public function __construct(
        public ?string $parent_id,
        public ?string $kind,
        public int $limit,
    ) {
    }

    /**
     * `parentId` em branco = raízes; `kind` precisa ser um dos de `LocationKindEnum`.
     *
     * @param array<string, mixed> $args
     * @return ListLocationsDTO
     * @throws ApiException
     */
    public static function fromArgs(array $args): ListLocationsDTO {
        $validated = ArgsValidator::validate($args, [
            'parentId' => 'nullable|string|max:64',
            'kind' => ['nullable', Rule::enum(LocationKindEnum::class)],
        ], [
            'parentId.*' => 'messages.locations.invalid_parent',
            'kind.*' => 'messages.locations.invalid_kind',
        ]);

        $parent = trim((string)($validated['parentId'] ?? ''));

        return new self(
            parent_id: $parent !== '' ? $parent : null,
            kind: $validated['kind'] ?? null,
            limit: LimitDTO::fromArg($args['limit'] ?? null, 20)->limit,
        );
    }
}
