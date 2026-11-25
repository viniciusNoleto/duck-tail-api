<?php

use Illuminate\Support\Str;
use Laudis\Neo4j\Exception\Neo4jException;
use Ramsey\Uuid\Uuid;

if (!function_exists('encode_cursor')) {
    /**
     * Cursor opaco das conexões GraphQL (`PlaceConnection`): base64 de `offset:N`, onde N é a posição
     * (a partir de 1) do último item entregue.
     *
     * @param int $offset
     * @return string
     */
    function encode_cursor(int $offset): string {
        return base64_encode('offset:' . $offset);
    }
}

if (!function_exists('decode_cursor')) {
    /**
     * Posição guardada num cursor de `encode_cursor`; `null` sem cursor (primeira página). Cursor que
     * não veio daqui devolve `false` (quem chama responde 422, não começa do zero em silêncio).
     *
     * @param string|null $cursor
     * @return int|false|null
     */
    function decode_cursor(?string $cursor): int|false|null {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = base64_decode($cursor, true);

        if (!is_string($decoded) || !preg_match('/^offset:(\d{1,9})$/', $decoded, $parts)) {
            return false;
        }

        return (int)$parts[1];
    }
}

if (!function_exists('escape_lucene')) {
    /**
     * Escapa a sintaxe do Lucene (índice de texto completo do Neo4j) no texto do usuário: a busca por
     * `igreja (matriz)` procura as palavras, não um grupo; `"`, `*`, `:` e afins não quebram a consulta.
     * É o `escape_like` da busca no grafo.
     *
     * @param string $value
     * @return string
     */
    function escape_lucene(string $value): string {
        return preg_replace('/([+\-!(){}\[\]^"~*?:\\\\\/]|&&|\|\|)/', '\\\\$1', $value) ?? '';
    }
}

if (!function_exists('lucene_prefix_query')) {
    /**
     * Consulta do índice `place_search` para o termo digitado: todas as palavras, cada uma como prefixo
     * (`ouro pre` → `ouro* AND pre*`). `null` quando não sobra palavra nenhuma.
     *
     * Sem acento e em minúsculas (`pão de açúcar` → `pao* AND de* AND acucar*`): o Lucene não passa termos
     * com curinga pelo analisador, então o `standard-folding` do índice (que guarda "acucar") não chega à
     * consulta; a dobra tem de ser feita aqui, senão `açúcar*` nunca encontra nada.
     *
     * @param string $term
     * @return string|null
     */
    function lucene_prefix_query(string $term): ?string {
        $words = preg_split('/\s+/u', mb_strtolower(Str::ascii(trim($term))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter(array_map(fn(string $word) => escape_lucene($word), $words), fn(string $word) => $word !== '');

        if ($words === []) {
            return null;
        }

        // `AND`/`OR`/`NOT` digitados viram palavras comuns (minúsculas não são operadores no Lucene).
        return implode(' AND ', array_map(fn(string $word) => $word . '*', $words));
    }
}

if (!function_exists('lucene_ranked_query')) {
    /**
     * A consulta de `lucene_prefix_query()` com reforço para o nome: encontra os mesmos nós (todas as
     * palavras como prefixo, em qualquer campo), mas o nome com as palavras exatas, em sequência, pesa
     * mais, e o nome com todas as palavras pesa mais que a descrição. Assim "Ouro Preto" vem antes de um
     * lugar que só cita Ouro Preto na descrição. `null` quando não sobra palavra nenhuma.
     *
     * @param string $term
     * @return string|null
     */
    function lucene_ranked_query(string $term): ?string {
        $prefix = lucene_prefix_query($term);

        if ($prefix === null) {
            return null;
        }

        $words = preg_split('/\s+/u', mb_strtolower(Str::ascii(trim($term))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $phrase = implode(' ', array_filter(array_map(fn(string $word) => escape_lucene($word), $words), fn(string $word) => $word !== ''));

        return "name:\"{$phrase}\"^8 OR name:({$prefix})^3 OR ({$prefix})";
    }
}

if (!function_exists('is_graph_constraint_violation')) {
    /**
     * Neo4j recusou a escrita por uma constraint (unicidade): o `23505` do grafo.
     *
     * @param Throwable $exception
     * @return bool
     */
    function is_graph_constraint_violation(Throwable $exception): bool {
        return $exception instanceof Neo4jException && $exception->getNeo4jCode() === 'Neo.ClientError.Schema.ConstraintValidationFailed';
    }
}

if (!function_exists('seed_id')) {
    /**
     * Id público (UUID v5, determinístico) de um nó de exemplo dos seeders. As constantes dos seeders
     * usam chaves legíveis (`seed-tour-aleijadinho`) só como referência interna; o id gravado no grafo é
     * opaco, porque o web põe o id na URL e um slug do nome em português não acompanha o idioma.
     * Determinístico para os seeders continuarem idempotentes (`MERGE` pelo id).
     *
     * @param string $key
     * @return string
     */
    function seed_id(string $key): string {
        return Uuid::uuid5('8f0d5c1e-3c1a-4f3b-9d6e-2b7a4c9e1f20', $key)->toString();
    }
}
