<?php

namespace App\Enum\Graph;

/**
 * Por que dois lugares estão ligados (`RELATED_TO.kind`, mais o tema em comum). O grafo guarda o tipo e o
 * valor (`kind: 'author', value: 'Aleijadinho'`); o texto do `RelatedPlace.reason` é montado na leitura,
 * no idioma da aplicação, a partir de `lang/*\/messages.php` (`messages.related.*`). Curadoria guarda o
 * texto pronto em `value`.
 */
enum RelationReasonEnum: string {
    case AUTHOR = 'author';
    case STYLE = 'style';
    case THEME = 'theme';
    case CURATED = 'curated';

    /**
     * @param string|null $kind
     * @param string|null $value
     * @return string
     */
    public static function describe(?string $kind, ?string $value): string {
        $reason = self::tryFrom((string)$kind) ?? self::CURATED;

        return $reason === self::CURATED ? (string)$value : __("messages.related.{$reason->value}", ['value' => (string)$value]);
    }
}
