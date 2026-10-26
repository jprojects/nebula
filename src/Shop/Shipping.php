<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;

/**
 * Portes. Cada forma de envío tiene una lista de reglas (shipping_methods.rules); para un carrito se usa
 * la PRIMERA regla que encaja, en el orden en que están. Si ninguna encaja, esa forma de envío no se ofrece.
 *
 * Regla:
 *   countries      "ES" o "ES,PT,AD" (vacío = cualquier país)
 *   postcodes      "07*, 35*, 38*" (prefijos), "08000-08999" (intervalos), "25001" (exacto); "!07*" excluye
 *                  (vacío = cualquier código postal)
 *   weight_min/max peso del pedido en kg (vacío = sin límite; el mínimo se incluye y el máximo no)
 *   subtotal_min/max  importe de los productos después de descuentos (ídem)
 *   price          precio del envío (IVA incluido)
 *   price_per_kg   suplemento por cada kg o fracción por encima de weight_min
 *   free_from      a partir de este importe, gratis
 *
 * Una forma de envío de recogida (pickup) sin reglas se ofrece siempre y es gratis.
 */
final class Shipping
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Formas de envío disponibles para un destino y un carrito, con su precio.
     * @return list<array{id:int,name:string,description:?string,delivery:?string,carrier:?string,pickup:bool,price:float}>
     */
    public function options(string $country, string $postcode, float $weight, float $subtotal, int $groupId): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM shipping_methods WHERE active = 1 ORDER BY sort, id') as $m) {
            $groups = Text::ids($m['group_ids']);
            if ($groups && !in_array($groupId, $groups, true)) {
                continue;
            }
            $rules = Text::json($m['rules']);
            $pickup = (bool) $m['pickup'];
            $price = null;
            if ($pickup && !$rules) {
                $price = 0.0;
            } else {
                foreach ($rules as $rule) {
                    if (is_array($rule) && ($pickup || $this->matches($rule, $country, $postcode, $weight, $subtotal))) {
                        $price = $this->price($rule, $weight, $subtotal);
                        break;
                    }
                }
            }
            if ($price === null) {
                continue;
            }
            $out[] = [
                'id' => (int) $m['id'],
                'name' => (string) $m['name'],
                'description' => $m['description'],
                'delivery' => $m['delivery'],
                'carrier' => $m['carrier'],
                'pickup' => $pickup,
                'price' => $price,
            ];
        }
        return $out;
    }

    public function matches(array $rule, string $country, string $postcode, float $weight, float $subtotal): bool
    {
        $countries = array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', (string) ($rule['countries'] ?? ''))));
        if ($countries && !in_array(strtoupper($country), $countries, true)) {
            return false;
        }
        if (!self::postcodeMatches((string) ($rule['postcodes'] ?? ''), $postcode)) {
            return false;
        }
        return self::inRange($weight, $rule['weight_min'] ?? null, $rule['weight_max'] ?? null)
            && self::inRange($subtotal, $rule['subtotal_min'] ?? null, $rule['subtotal_max'] ?? null);
    }

    public function price(array $rule, float $weight, float $subtotal): float
    {
        $free = self::num($rule['free_from'] ?? null);
        if ($free !== null && $free > 0 && $subtotal >= $free) {
            return 0.0;
        }
        $price = (float) (self::num($rule['price'] ?? null) ?? 0);
        $perKg = (float) (self::num($rule['price_per_kg'] ?? null) ?? 0);
        if ($perKg > 0) {
            $over = $weight - (float) (self::num($rule['weight_min'] ?? null) ?? 0);
            $price += $over > 0 ? ceil(round($over, 3)) * $perKg : 0;
        }
        return Money::round(max(0, $price));
    }

    /** Lista de patrones separada por comas; los "!" excluyen. Sin patrones de inclusión = todos. */
    public static function postcodeMatches(string $patterns, string $postcode): bool
    {
        $postcode = strtoupper(str_replace(' ', '', trim($postcode)));
        $include = [];
        $exclude = [];
        foreach (preg_split('/[,;\n]+/', $patterns) ?: [] as $p) {
            $p = strtoupper(str_replace(' ', '', trim($p)));
            if ($p === '') {
                continue;
            }
            str_starts_with($p, '!') ? $exclude[] = substr($p, 1) : $include[] = $p;
        }
        foreach ($exclude as $p) {
            if (self::postcodeIs($p, $postcode)) {
                return false;
            }
        }
        if (!$include) {
            return true;
        }
        foreach ($include as $p) {
            if (self::postcodeIs($p, $postcode)) {
                return true;
            }
        }
        return false;
    }

    private static function postcodeIs(string $pattern, string $postcode): bool
    {
        if ($postcode === '') {
            return false;
        }
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($postcode, rtrim($pattern, '*'));
        }
        if (preg_match('/^([0-9A-Z]+)-([0-9A-Z]+)$/', $pattern, $m)) {
            if (ctype_digit($m[1]) && ctype_digit($m[2]) && ctype_digit($postcode)) {
                return (int) $postcode >= (int) $m[1] && (int) $postcode <= (int) $m[2];
            }
            return strcmp($postcode, $m[1]) >= 0 && strcmp($postcode, $m[2]) <= 0;
        }
        return $pattern === $postcode;
    }

    private static function inRange(float $value, mixed $min, mixed $max): bool
    {
        $min = self::num($min);
        $max = self::num($max);
        return ($min === null || $value >= $min) && ($max === null || $value < $max);
    }

    private static function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = is_string($v) ? str_replace(',', '.', $v) : $v;
        return is_numeric($v) ? (float) $v : null;
    }
}
