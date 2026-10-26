<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;

/**
 * Descuentos y promociones.
 *
 * - Sin código: se aplican solas a quien cumpla las condiciones. Si no tienen importe mínimo, el precio rebajado
 *   ya se ve en el catálogo (displayPrice).
 * - Con código (cupón): solo si el cliente lo introduce en el carrito.
 * - Tipos: percent (porcentaje sobre las líneas a las que se aplica), fixed (importe fijo repartido entre ellas)
 *   y free_shipping (portes gratis).
 * - Alcance: todo el carrito, unas categorías o unos productos. Grupos de clientes, fechas, importe mínimo y
 *   número de usos (en total y por cliente).
 * - Combinables: todas las que se pueden aplicar a la vez. Si alguna no es combinable, se queda la opción que más
 *   ahorra: o esa promoción sola, o la suma de las combinables.
 */
final class Promotions
{
    private ?array $active = null;

    public function __construct(private readonly Database $db)
    {
    }

    /** Promociones activas ahora (con o sin código). */
    public function active(): array
    {
        if ($this->active === null) {
            $now = date('Y-m-d H:i:s');
            $this->active = array_map([$this, 'normalize'], $this->db->fetchAll(
                'SELECT * FROM promotions WHERE active = 1 AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at >= ?)
                 AND (max_uses IS NULL OR uses < max_uses) ORDER BY id',
                [$now, $now]
            ));
        }
        return $this->active;
    }

    public function findByCode(string $code): ?array
    {
        $code = mb_strtoupper(trim($code));
        foreach ($this->active() as $p) {
            if ($p['code'] !== null && mb_strtoupper($p['code']) === $code) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Precio rebajado por las promociones automáticas sin mínimo (para el catálogo).
     * @param list<int> $categoryIds categorías del producto
     * @return array{price:float,label:?string}
     */
    public function displayPrice(int $productId, array $categoryIds, float $price, int $groupId): array
    {
        $best = ['price' => $price, 'label' => null];
        foreach ($this->active() as $p) {
            if ($p['code'] !== null || $p['min_subtotal'] > 0 || $p['type'] === 'free_shipping' || !$this->groupAllowed($p, $groupId)
                || !$this->appliesToProduct($p, $productId, $categoryIds)) {
                continue;
            }
            $discounted = $p['type'] === 'percent'
                ? Money::round($price * (1 - min(100, $p['value']) / 100))
                : Money::round(max(0, $price - $p['value']));
            if ($discounted < $best['price']) {
                $best = ['price' => $discounted, 'label' => $p['label']];
            }
        }
        return $best;
    }

    /**
     * Calcula las promociones de un carrito.
     *
     * @param list<array{key:string,product_id:int,categories:list<int>,total:float}> $lines
     * @param array{group_id:int,email:?string,customer_id:?int,coupon:?string} $ctx
     * @return array{applied:list<array{id:int,label:string,type:string,amount:float,code:?string}>,
     *               line_discounts:array<string,float>, free_shipping:bool, coupon_error:?string}
     */
    public function apply(array $lines, float $subtotal, array $ctx): array
    {
        $candidates = [];
        $couponError = null;
        $coupon = isset($ctx['coupon']) && trim((string) $ctx['coupon']) !== '' ? $this->findByCode((string) $ctx['coupon']) : null;
        if (!empty($ctx['coupon']) && $coupon === null) {
            $couponError = 'coupon_invalid';
        }

        foreach ($this->active() as $p) {
            $isCoupon = $p['code'] !== null;
            if ($isCoupon && ($coupon === null || $coupon['id'] !== $p['id'])) {
                continue;
            }
            $reason = $this->rejectReason($p, $lines, $subtotal, $ctx);
            if ($reason !== null) {
                if ($isCoupon) {
                    $couponError = $reason;
                }
                continue;
            }
            $eligible = array_values(array_filter($lines, fn ($l) => $this->appliesToProduct($p, $l['product_id'], $l['categories'])));
            $base = array_sum(array_column($eligible, 'total'));
            $amount = match ($p['type']) {
                'percent' => Money::round($base * min(100, $p['value']) / 100),
                'fixed' => Money::round(min($p['value'], $base)),
                default => 0.0,
            };
            $candidates[] = ['promo' => $p, 'eligible' => $eligible, 'amount' => $amount];
        }

        // Combinables frente a la mejor no combinable
        $combinable = array_values(array_filter($candidates, fn ($c) => $c['promo']['combinable']));
        $exclusive = array_values(array_filter($candidates, fn ($c) => !$c['promo']['combinable']));
        $chosen = $combinable;
        if ($exclusive) {
            usort($exclusive, fn ($a, $b) => $b['amount'] <=> $a['amount']);
            $sumCombinable = array_sum(array_column($combinable, 'amount'));
            if ($exclusive[0]['amount'] > $sumCombinable || !$combinable) {
                $chosen = [$exclusive[0]];
            }
            if ($coupon !== null && !in_array($coupon['id'], array_map(fn ($c) => $c['promo']['id'], $chosen), true)
                && in_array($coupon['id'], array_map(fn ($c) => $c['promo']['id'], $candidates), true)) {
                $couponError ??= 'coupon_not_combinable';
            }
        }

        // Reparto del descuento entre las líneas (para el IVA de cada tipo y la factura)
        $lineDiscounts = array_fill_keys(array_column($lines, 'key'), 0.0);
        $remaining = array_combine(array_column($lines, 'key'), array_column($lines, 'total')) ?: [];
        $applied = [];
        $freeShipping = false;
        foreach ($chosen as $c) {
            $p = $c['promo'];
            if ($p['type'] === 'free_shipping') {
                $freeShipping = true;
                $applied[] = ['id' => $p['id'], 'label' => $p['label'], 'type' => $p['type'], 'amount' => 0.0, 'code' => $p['code']];
                continue;
            }
            $amount = $p['type'] === 'percent'
                ? Money::round(array_sum(array_map(fn ($l) => $remaining[$l['key']], $c['eligible'])) * min(100, $p['value']) / 100)
                : Money::round(min($p['value'], array_sum(array_map(fn ($l) => $remaining[$l['key']], $c['eligible']))));
            if ($amount <= 0) {
                continue;
            }
            $this->distribute($amount, $c['eligible'], $remaining, $lineDiscounts);
            $applied[] = ['id' => $p['id'], 'label' => $p['label'], 'type' => $p['type'], 'amount' => $amount, 'code' => $p['code']];
        }

        return ['applied' => $applied, 'line_discounts' => $lineDiscounts, 'free_shipping' => $freeShipping, 'coupon_error' => $couponError];
    }

    /** Registra el uso de las promociones de un pedido (dentro de la transacción del pedido). */
    public function recordUses(array $applied, int $orderId, ?int $customerId, string $email): void
    {
        foreach ($applied as $a) {
            $this->db->insert('promotion_uses', [
                'promotion_id' => (int) $a['id'],
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'email' => mb_strtolower($email),
                'amount' => $a['amount'],
            ]);
            $this->db->query('UPDATE promotions SET uses = uses + 1 WHERE id = ?', [(int) $a['id']]);
        }
    }

    // ---------------------------------------------------------------- reglas

    private function rejectReason(array $p, array $lines, float $subtotal, array $ctx): ?string
    {
        if (!$this->groupAllowed($p, (int) ($ctx['group_id'] ?? 0))) {
            return 'coupon_group';
        }
        if ($p['min_subtotal'] > 0 && $subtotal < $p['min_subtotal']) {
            return 'coupon_min';
        }
        $any = false;
        foreach ($lines as $l) {
            if ($this->appliesToProduct($p, $l['product_id'], $l['categories'])) {
                $any = true;
                break;
            }
        }
        if (!$any) {
            return 'coupon_products';
        }
        if ($p['max_uses_customer'] !== null && !empty($ctx['email'])) {
            $used = (int) $this->db->fetchValue(
                'SELECT COUNT(*) FROM promotion_uses u JOIN orders o ON o.id = u.order_id
                 WHERE u.promotion_id = ? AND u.email = ? AND o.status NOT IN (\'cancelled\')',
                [$p['id'], mb_strtolower((string) $ctx['email'])]
            );
            if ($used >= $p['max_uses_customer']) {
                return 'coupon_used';
            }
        }
        return null;
    }

    private function groupAllowed(array $p, int $groupId): bool
    {
        return !$p['groups'] || in_array($groupId, $p['groups'], true);
    }

    private function appliesToProduct(array $p, int $productId, array $categoryIds): bool
    {
        return match ($p['scope']) {
            'products' => in_array($productId, $p['products'], true),
            'categories' => (bool) array_intersect($categoryIds, $p['categories']),
            default => true,
        };
    }

    /** Reparte $amount entre las líneas en proporción a lo que queda de cada una (el redondeo va a la última). */
    private function distribute(float $amount, array $eligible, array &$remaining, array &$discounts): void
    {
        $base = array_sum(array_map(fn ($l) => $remaining[$l['key']], $eligible));
        if ($base <= 0) {
            return;
        }
        $left = $amount;
        foreach (array_values($eligible) as $i => $l) {
            $part = $i === count($eligible) - 1 ? $left : Money::round($amount * $remaining[$l['key']] / $base);
            $part = min($part, $remaining[$l['key']]);
            $discounts[$l['key']] = Money::round($discounts[$l['key']] + $part);
            $remaining[$l['key']] = Money::round($remaining[$l['key']] - $part);
            $left = Money::round($left - $part);
        }
    }

    private function normalize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'label' => trim((string) ($row['label'] ?? '')) !== '' ? (string) $row['label'] : (string) $row['name'],
            'code' => $row['code'] !== null && trim((string) $row['code']) !== '' ? trim((string) $row['code']) : null,
            'type' => in_array($row['type'], ['percent', 'fixed', 'free_shipping'], true) ? $row['type'] : 'percent',
            'value' => (float) $row['value'],
            'scope' => in_array($row['scope'], ['all', 'categories', 'products'], true) ? $row['scope'] : 'all',
            'categories' => Text::ids($row['categories']),
            'products' => Text::ids($row['products']),
            'groups' => Text::ids($row['group_ids']),
            'min_subtotal' => (float) ($row['min_subtotal'] ?? 0),
            'max_uses_customer' => $row['max_uses_customer'] !== null ? (int) $row['max_uses_customer'] : null,
            'combinable' => (bool) $row['combinable'],
        ];
    }
}
