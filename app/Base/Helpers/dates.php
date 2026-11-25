<?php

use Illuminate\Support\Carbon;

if (!function_exists('business_now')) {
    /**
     * Agora no fuso do negócio (`app.business_timezone`). Os timestamps continuam gravados em UTC;
     * este fuso só decide qual é o dia de "hoje" nas regras de calendário.
     *
     * @return Carbon
     */
    function business_now(): Carbon {
        return now()->setTimezone(config('app.business_timezone'));
    }
}

if (!function_exists('business_today')) {
    /**
     * Dia de hoje (`Y-m-d`) no fuso do negócio: a data gravada numa visita (`VISITED.date`). Às 22h em
     * São Paulo ainda é hoje, mesmo que em UTC já seja amanhã.
     *
     * @return string
     */
    function business_today(): string {
        return business_now()->toDateString();
    }
}
