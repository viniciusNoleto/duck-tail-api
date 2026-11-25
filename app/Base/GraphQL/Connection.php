<?php

namespace App\Base\GraphQL;

use App\Data\Graph\Common\PageDTO;

/**
 * Monta uma conexão (`{ edges: [{ node, cursor }], pageInfo: { hasNextPage, endCursor } }`) a partir de
 * uma fatia de até `first + 1` itens: o item a mais só diz que existe próxima página.
 */
final class Connection {

    /**
     * @param list<array<string, mixed>> $items
     * @param PageDTO $page
     * @return array{edges: list<array{node: array<string, mixed>, cursor: string}>, pageInfo: array{hasNextPage: bool, endCursor: string|null}}
     */
    public static function fromSlice(array $items, PageDTO $page): array {
        $edges = [];

        foreach (array_slice($items, 0, $page->first) as $index => $node) {
            $edges[] = ['node' => $node, 'cursor' => encode_cursor($page->offset + $index + 1)];
        }

        return [
            'edges' => $edges,
            'pageInfo' => [
                'hasNextPage' => count($items) > $page->first,
                'endCursor' => $edges === [] ? null : $edges[array_key_last($edges)]['cursor'],
            ],
        ];
    }
}
