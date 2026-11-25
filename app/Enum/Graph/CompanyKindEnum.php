<?php

namespace App\Enum\Graph;

/**
 * Tipo de empresa de turismo (`Company.kind`). O web mostra o rótulo; o grafo guarda o slug.
 */
enum CompanyKindEnum: string {
    case AGENCY = 'agency';
    case GUIDE = 'guide';
    case LODGING = 'lodging';
    case TRANSPORT = 'transport';
}
