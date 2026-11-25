<?php

namespace App\Actions\Graph\Category;

use App\Base\Graph\Graph;

final class NameCategoriesAction {

    /**
     * Nome das categorias de exemplo em espanhol e inglês (as importadas trazem do Wikidata). Chave: o
     * nome em pt-br, que é o `name` do nó.
     */
    public const SEED_NAMES = [
        'aquário' => ['acuario', 'aquarium'],
        'aqueduto' => ['acueducto', 'aqueduct'],
        'arte urbana' => ['arte urbano', 'street art'],
        'avenida' => ['avenida', 'avenue'],
        'biblioteca' => ['biblioteca', 'library'],
        'conjunto arquitetônico' => ['conjunto arquitectónico', 'architectural ensemble'],
        'edifício' => ['edificio', 'building'],
        'estádio' => ['estadio', 'stadium'],
        'forte' => ['fuerte', 'fort'],
        'igreja' => ['iglesia', 'church'],
        'ilha' => ['isla', 'island'],
        'jardim botânico' => ['jardín botánico', 'botanical garden'],
        'marco histórico' => ['hito histórico', 'historic landmark'],
        'mercado' => ['mercado', 'market'],
        'mina' => ['mina', 'mine'],
        'mirante' => ['mirador', 'viewpoint'],
        'monumento' => ['monumento', 'monument'],
        'mosteiro' => ['monasterio', 'monastery'],
        'museu' => ['museo', 'museum'],
        'palácio' => ['palacio', 'palace'],
        'parque' => ['parque', 'park'],
        'praça' => ['plaza', 'square'],
        'santuário' => ['santuario', 'shrine'],
        'teatro' => ['teatro', 'theatre'],
        'trem turístico' => ['tren turístico', 'heritage railway'],
    ];

    public function __construct(private readonly Graph $graph) {
    }

    /**
     * Grava o nome em espanhol (`nameEsMx`) e em inglês (`nameEnUs`) das categorias dadas, só onde ainda
     * não há (o que veio do Wikidata não é sobrescrito). Categoria que não existe é ignorada.
     *
     * @param array<string, array{0: string, 1: string}> $names pt-br => [es, en]
     * @return int quantas categorias foram encontradas
     */
    public function handle(array $names = self::SEED_NAMES): int {
        $rows = array_map(fn(string $name, array $translations) => ['name' => $name, 'es' => $translations[0], 'en' => $translations[1]], array_keys($names), $names);

        $result = $this->graph->write(
            'UNWIND $rows AS row
             MATCH (k:Category {name: row.name})
             SET k.nameEsMx = coalesce(k.nameEsMx, row.es), k.nameEnUs = coalesce(k.nameEnUs, row.en)
             RETURN count(k) AS affected',
            ['rows' => $rows]
        );

        return (int)($result[0]['affected'] ?? 0);
    }
}
