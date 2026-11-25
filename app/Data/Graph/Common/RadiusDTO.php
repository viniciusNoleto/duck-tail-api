<?php

namespace App\Data\Graph\Common;

use App\Base\GraphQL\ArgsValidator;
use App\Exceptions\ApiException;
use Spatie\LaravelData\Data;

/**
 * Raio de busca: chega em km (contrato) e vai em metros para o Cypher (`point.distance` devolve metros).
 * O teto por campo limita o trabalho de uma consulta (um raio de 5.000 km varreria o grafo inteiro).
 */
class RadiusDTO extends Data {

    public function __construct(
        public float $meters,
    ) {
    }

    /**
     * @param mixed $km
     * @param float $default_km
     * @param float $max_km
     * @return RadiusDTO
     * @throws ApiException
     */
    public static function fromKm(mixed $km, float $default_km, float $max_km): RadiusDTO {
        $validated = ArgsValidator::validate(['radiusKm' => $km ?? $default_km], [
            'radiusKm' => 'required|numeric|gt:0|max:' . $max_km,
        ], [
            'radiusKm.*' => 'messages.places.invalid_radius',
        ], ['max' => $max_km]);

        return new self(meters: (float)$validated['radiusKm'] * 1000);
    }
}
