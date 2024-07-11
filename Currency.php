<?php

declare(strict_types=1);

class Currency
{
    const CB_URL = "https://cbu.uz/uz/arkhiv-kursov-valyut/json/";

    public function exchange(float $amount, string $from_currency, string $to_currency): ?float
    {
        $content = @file_get_contents(self::CB_URL);
        if ($content === false) {
            return null;
        }

        $rates = json_decode($content, true);

        if ($rates !== null) {
            $from_rate = null;
            $to_rate = null;

            if ($from_currency === 'UZS') {
                $from_rate = 1;
            }
            if ($to_currency === 'UZS') {
                $to_rate = 1;
            }

            foreach ($rates as $rate) {
                if ($rate['Ccy'] === $from_currency) {
                    $from_rate = floatval($rate['Rate']);
                }
                if ($rate['Ccy'] === $to_currency) {
                    $to_rate = floatval($rate['Rate']);
                }
                if ($from_rate !== null && $to_rate !== null) {
                    break;
                }
            }

            if ($from_rate !== null && $to_rate !== null) {
                if ($from_currency === 'UZS') {
                    $converted = $amount / $to_rate;
                } elseif ($to_currency === 'UZS') {
                    $converted = $amount * $from_rate;
                } else {
                    $converted = ($amount * $from_rate) / $to_rate;
                }
                return round($converted, 2);
            }
        }
        return null;
    }
}
