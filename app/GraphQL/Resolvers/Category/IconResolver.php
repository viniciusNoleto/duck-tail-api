<?php

namespace App\GraphQL\Resolvers\Category;

use App\Enum\Graph\CategoryIconEnum;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * `Category.icon`: o nome do ícone do Iconify, escolhido pelo nome da categoria (`CategoryIconEnum`).
 * Sem consulta: sai da projeção que o pai já trouxe.
 */
final class IconResolver {

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return string
     */
    public function __invoke(array $root, array $args, GraphQLContext $context, ResolveInfo $info): string {
        $names = is_array($root['names'] ?? null) ? $root['names'] : [];

        return CategoryIconEnum::for($names['ptBr'] ?? $root['name'] ?? null, $names['enUs'] ?? null)->value;
    }
}
