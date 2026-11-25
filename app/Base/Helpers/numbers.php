<?php

if (!function_exists('parse_id')) {
    /**
     * Converte um id vindo da rota/payload em inteiro positivo; `null` quando não é um id válido.
     *
     * @param mixed $value
     * @return int|null
     */
    function parse_id(mixed $value): ?int {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        return null;
    }
}
