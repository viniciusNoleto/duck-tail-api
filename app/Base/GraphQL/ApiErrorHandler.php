<?php

namespace App\Base\GraphQL;

use App\Exceptions\ApiException;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Nuwave\Lighthouse\Execution\ErrorHandler;
use Throwable;

/**
 * O `Response::returnError` do GraphQL: todo erro sai com a mensagem traduzida e o mesmo vocabulário do
 * REST. `message` no idioma da aplicação; em `extensions`: `code`, `category`, `status` (o HTTP
 * equivalente), `key` (chave de `lang/*\/messages.php`) e `messages` (`{ "pt-br", "es-mx", "en-us" }`).
 *
 * - `ApiException` (regra de negócio, validação, não encontrado) → status da exceção, sem log;
 * - erro do próprio documento GraphQL (sintaxe, campo inexistente) → como o Lighthouse/webonyx manda;
 * - qualquer outro → 500 `messages.server_error`, reportado (log com `request_id`/`user_id`).
 */
final class ApiErrorHandler implements ErrorHandler {

    private const CODES = [
        401 => ['UNAUTHENTICATED', 'authentication'],
        403 => ['FORBIDDEN', 'authorization'],
        404 => ['NOT_FOUND', 'not_found'],
        409 => ['CONFLICT', 'conflict'],
        422 => ['VALIDATION', 'validation'],
        429 => ['TOO_MANY_REQUESTS', 'rate_limit'],
        500 => ['INTERNAL', 'internal'],
    ];

    /**
     * @param Error|null $error
     * @param \Closure $next
     * @return array<string, mixed>|null
     */
    public function __invoke(?Error $error, \Closure $next): ?array {
        if ($error === null) {
            return $next(null);
        }

        $previous = $error->getPrevious();

        if ($previous instanceof ApiException) {
            return $next(self::translated($error, $previous->message_key, $previous->status_code, $previous->replace));
        }

        if ($previous instanceof AuthenticationException) {
            return $next(self::translated($error, 'messages.unauthorized', 401));
        }

        if ($previous === null && $error->isClientSafe()) {
            return $next($error);
        }

        report($previous ?? $error);

        return $next(self::translated($error, 'messages.server_error', 500, debug: $previous ?? $error));
    }

    /**
     * @param Error $error
     * @param string $key
     * @param int $status
     * @param array<string, string|int|float> $replace
     * @param Throwable|null $debug
     * @return Error
     */
    private static function translated(Error $error, string $key, int $status, array $replace = [], ?Throwable $debug = null): Error {
        [$code, $category] = self::CODES[$status] ?? ['ERROR', 'error'];

        $extensions = [
            'code' => $code,
            'category' => $category,
            'status' => $status,
            'key' => $key,
            'messages' => api_message($key, $replace),
        ];

        if ($debug && config('logging.show_error_message')) {
            $extensions['message_error'] = $debug->getMessage();
        }

        return new Error(
            message: __($key, $replace),
            nodes: $error->getNodes(),
            source: $error->getSource(),
            positions: $error->getPositions(),
            path: $error->getPath(),
            previous: null,
            extensions: $extensions,
        );
    }
}
