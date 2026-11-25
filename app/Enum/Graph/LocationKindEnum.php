<?php

namespace App\Enum\Graph;

/**
 * Nível de uma localidade (`Location.kind`). Uma localidade pode ser filha de outra de qualquer nível
 * (`WITHIN`): país → estado → (região) → cidade. O web mostra o rótulo; o grafo guarda o slug.
 */
enum LocationKindEnum: string {
    case COUNTRY = 'country';
    case STATE = 'state';
    case REGION = 'region';
    case CITY = 'city';
}
