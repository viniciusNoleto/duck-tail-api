<?php

namespace App\Base\GraphQL;

use App\Exceptions\ApiException;
use App\Models\User;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Usuário da sessão nos resolvers. O token já foi conferido pelo `OptionalAuthenticateMiddleware`
 * (inválido → 401 antes do GraphQL); aqui só falta decidir se o campo exige sessão.
 */
final class GraphAuth {

    /**
     * @param GraphQLContext $context
     * @return User|null
     */
    public static function user(GraphQLContext $context): ?User {
        $user = $context->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Campo que exige sessão: sem usuário → erro `UNAUTHENTICATED` (`messages.unauthorized`).
     *
     * @param GraphQLContext $context
     * @return User
     * @throws ApiException
     */
    public static function require(GraphQLContext $context): User {
        return self::user($context) ?? throw new ApiException('messages.unauthorized', 401);
    }
}
