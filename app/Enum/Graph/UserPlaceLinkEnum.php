<?php

namespace App\Enum\Graph;

/**
 * Relações de um `User` com um `Place`. O valor é o tipo da relação no grafo (interpolado no Cypher:
 * tipo de relação não pode ser parâmetro, e só valores deste enum chegam lá).
 */
enum UserPlaceLinkEnum: string {
    case VISITED = 'VISITED';
    case WANTS_TO_VISIT = 'WANTS_TO_VISIT';
}
