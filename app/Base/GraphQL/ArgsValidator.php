<?php

namespace App\Base\GraphQL;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Validator;

/**
 * O `BaseRequest` do GraphQL: valida os argumentos de um campo com as regras do Laravel e `messages()`
 * com **chaves** de `lang/*\/messages.php`. A primeira falha vira o erro `VALIDATION` (422) do campo.
 * Os DTOs chamam isto em `fromArgs()`, antes de normalizar.
 */
final class ArgsValidator {

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string|int|float> $replace substituições da mensagem (ex.: `:max`)
     * @return array<string, mixed> só o que foi validado
     * @throws ApiException
     */
    public static function validate(array $args, array $rules, array $messages = [], array $replace = []): array {
        $validator = Validator::make($args, $rules, $messages);

        if ($validator->fails()) {
            $first = (string)$validator->errors()->first();

            throw new ApiException(is_message_key($first) ? $first : 'messages.invalid_payload', 422, $replace);
        }

        return $validator->validated();
    }
}
