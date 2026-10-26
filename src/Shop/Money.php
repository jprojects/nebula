<?php
declare(strict_types=1);

namespace Nebula\Shop;

/**
 * Importes. Se trabaja siempre con float redondeado a 2 decimales (los importes vienen de DECIMAL(12,2))
 * y se redondea en cada paso que produce un importe nuevo.
 */
final class Money
{
    private const SYMBOLS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'CHF' => 'CHF'];

    public static function round(float $amount): float
    {
        return round($amount + 0.0, 2);
    }

    /** 1234.5 → "1.234,50 €" (o el formato del idioma si hay intl). */
    public static function format(float $amount, ?string $locale = null, ?string $currency = null): string
    {
        $currency ??= ShopConfig::currency();
        if (class_exists(\NumberFormatter::class)) {
            $fmt = new \NumberFormatter(self::intlLocale($locale), \NumberFormatter::CURRENCY);
            $out = $fmt->formatCurrency($amount, $currency);
            if ($out !== false) {
                return str_replace("\u{00A0}", ' ', $out);
            }
        }
        $symbol = self::SYMBOLS[$currency] ?? $currency;
        return number_format($amount, 2, ',', '.') . ' ' . $symbol;
    }

    /** Importe para formularios y pasarelas: "1234.50". */
    public static function plain(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /** Céntimos enteros (Redsys). */
    public static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /** IVA incluido en un importe con IVA. */
    public static function includedTax(float $gross, float $rate): float
    {
        return $rate > 0 ? self::round($gross - $gross / (1 + $rate / 100)) : 0.0;
    }

    private static function intlLocale(?string $locale): string
    {
        return match ($locale) {
            'ca' => 'ca_ES',
            'en' => 'en_GB',
            'es', null => 'es_ES',
            default => $locale,
        };
    }
}
