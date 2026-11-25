<?php

namespace App\Base\GraphQL;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;

/**
 * Evita o N+1 dos campos de listas: cada resolver chama `load($chave)` e recebe um `Deferred`; quando o
 * webonyx resolve o primeiro, **uma** consulta (`$fetch`, que recebe todas as chaves pendentes e devolve
 * `[chave => valor]`) atende todos. Ex.: `searchPlaces { edges { node { city { name } } } }` com 20
 * lugares faz 2 consultas ao grafo, não 21.
 *
 * Uma instância por campo do documento (caminho sem índices de lista) **e** por argumentos (`nearby(radiusKm: 1)`
 * e `nearby(radiusKm: 5)` no mesmo documento não se misturam).
 */
final class BatchLoader {

    /** @var array<string, true> chaves pedidas e ainda não buscadas */
    private array $pending = [];

    /** @var array<string, mixed> */
    private array $loaded = [];

    /**
     * @param Closure(list<string>): array<string, mixed> $fetch
     */
    public function __construct(
        private readonly Closure $fetch,
    ) {
    }

    /**
     * @param ResolveInfo $info
     * @param array<string, mixed> $args
     * @param Closure(list<string>): array<string, mixed> $fetch
     * @return self
     */
    public static function for(ResolveInfo $info, array $args, Closure $fetch): self {
        $path = [...$info->path, md5(serialize($args))];

        return BatchLoaderRegistry::instance($path, fn() => new self($fetch));
    }

    /**
     * @param string|int $key
     * @param mixed $default valor para chave que a consulta não devolveu (ex.: `[]`, `null`)
     * @return Deferred
     */
    public function load(string|int $key, mixed $default = null): Deferred {
        $key = (string)$key;

        if (!array_key_exists($key, $this->loaded)) {
            $this->pending[$key] = true;
        }

        return new Deferred(function () use ($key, $default) {
            if (isset($this->pending[$key])) {
                $keys = array_map(strval(...), array_keys($this->pending));
                $this->pending = [];
                $fetched = ($this->fetch)($keys);

                foreach ($keys as $pending_key) {
                    $this->loaded[$pending_key] = $fetched[$pending_key] ?? null;
                }
            }

            return $this->loaded[$key] ?? $default;
        });
    }
}
