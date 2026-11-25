<?php

namespace App\Actions\Graph\Category;

use App\Base\Graph\Graph;

final class AssignCategoryIdsAction {

    public const BATCH_SIZE = 500;

    /** Formato do id público de uma categoria (o `randomUUID()` do Neo4j, minúsculo). */
    public const UUID_PATTERN = '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$';

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Dá um `id` público opaco (UUID) às categorias que ainda não têm e troca o das que ainda usam o
     * formato antigo (o slug do nome: a URL do web mostrava o nome em português, que não acompanha o
     * idioma). A importação e os seeders já criam a categoria com UUID (`ON CREATE SET k.id =
     * randomUUID()`); este passo cobre o que veio antes disso e roda depois deles e no
     * `ducktail:graph-migrate`. Em lotes; idempotente: categoria com UUID não muda.
     *
     * @return int quantas categorias receberam id
     */
    public function handle(): int {
        return $this->graph->writeUntilDone(
            'MATCH (c:Category) WHERE c.id IS NULL OR NOT c.id =~ $pattern
             WITH c LIMIT $limit
             SET c.id = randomUUID()
             RETURN count(c) AS affected',
            ['pattern' => self::UUID_PATTERN],
            self::BATCH_SIZE
        );
    }
}
