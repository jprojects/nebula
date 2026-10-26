<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;
use Nebula\Shop\Payments\PaymentMethods;

/**
 * Valora un carrito: precios del grupo, existencias, promociones, portes, forma de pago e IVA.
 * Lo usan el carrito, el checkout (cada vez que cambia la dirección, el envío o el pago) y la creación
 * del pedido, que vuelve a calcularlo todo en el servidor.
 */
final class CartCalculator
{
    public function __construct(
        private readonly Database $db,
        private readonly Pricing $pricing,
        private readonly Promotions $promotions,
        private readonly Shipping $shipping,
        private readonly PaymentMethods $payments,
    ) {
    }

    /**
     * @param array<string,array{product_id:int,color:?string,qty:int}> $lines líneas del carrito
     * @param array{group:array,coupon?:?string,email?:?string,customer_id?:?int,country?:?string,postcode?:?string,
     *              shipping_id?:?int,payment_id?:?int} $ctx
     * @return array resumen (ver el final del método); 'lines' trae las líneas corregidas para guardar en el carrito
     */
    public function quote(array $lines, array $ctx): array
    {
        $group = $ctx['group'];
        $groupId = (int) $group['id'];
        $notices = [];

        $ids = array_values(array_unique(array_map(fn ($l) => (int) $l['product_id'], $lines)));
        $rows = [];
        if ($ids) {
            foreach ($this->db->fetchAll(
                'SELECT * FROM products WHERE active = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                $ids
            ) as $r) {
                $rows[(int) $r['id']] = $r;
            }
            $this->pricing->preload($ids, $groupId);
        }

        // Líneas válidas y existencias (compartidas por todos los colores del producto)
        $clean = [];
        $stockLeft = [];
        foreach ($lines as $key => $l) {
            $row = $rows[(int) $l['product_id']] ?? null;
            if ($row === null) {
                $notices[] = ['type' => 'removed', 'name' => null];
                continue;
            }
            $colors = Catalog::colors($row);
            $color = $l['color'] !== null && $l['color'] !== '' ? Catalog::color($row, $l['color']) : null;
            if (($colors && $color === null) || (!$colors && $l['color'] !== null && $l['color'] !== '')) {
                $notices[] = ['type' => 'removed', 'name' => $row['name']];
                continue;
            }
            $qty = max(1, (int) $l['qty']);
            if ($row['stock'] !== null) {
                $stockLeft[$row['id']] ??= max(0, (int) $row['stock']);
                if ($stockLeft[$row['id']] <= 0) {
                    $notices[] = ['type' => 'out_of_stock', 'name' => $row['name']];
                    continue;
                }
                if ($qty > $stockLeft[$row['id']]) {
                    $qty = $stockLeft[$row['id']];
                    $notices[] = ['type' => 'stock_limited', 'name' => $row['name'], 'qty' => $qty];
                }
                $stockLeft[$row['id']] -= $qty;
            }
            $clean[$key] = ['product_id' => (int) $row['id'], 'color' => $color['code'] ?? null, 'qty' => $qty, 'row' => $row, 'colorRow' => $color];
        }

        // Las escalas por cantidad cuentan todas las unidades del producto
        $qtyByProduct = [];
        foreach ($clean as $l) {
            $qtyByProduct[$l['product_id']] = ($qtyByProduct[$l['product_id']] ?? 0) + $l['qty'];
        }

        $items = [];
        $promoLines = [];
        foreach ($clean as $key => $l) {
            $row = $l['row'];
            $c = $l['colorRow'];
            $unit = $this->pricing->unitPrice($row, $c, $qtyByProduct[$l['product_id']], $group);
            $total = Money::round($unit * $l['qty']);
            $categories = Text::ids($row['categories']);
            $images = $c['images'] ?? [];
            $items[$key] = [
                'key' => (string) $key,
                'product_id' => $l['product_id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'sku' => $c['sku'] ?? $row['sku'],
                'color_code' => $c['code'] ?? null,
                'color_name' => $c['name'] ?? null,
                'color_hex' => $c['hex'] ?? null,
                'image' => $images[0] ?? (Text::ids($row['images'])[0] ?? null),
                'qty' => $l['qty'],
                'max_qty' => $row['stock'] !== null ? (int) $row['stock'] : Cart::MAX_QTY,
                'unit_price' => $unit,
                'total' => $total,
                'discount' => 0.0,
                'tax_rate' => (float) $row['tax_rate'],
                'weight' => Money::round((float) $row['weight'] * $l['qty'] * 1000) / 1000,
            ];
            $promoLines[] = ['key' => (string) $key, 'product_id' => $l['product_id'], 'categories' => $categories, 'total' => $total];
        }
        $subtotal = Money::round(array_sum(array_column($items, 'total')));

        $promo = $this->promotions->apply($promoLines, $subtotal, [
            'group_id' => $groupId,
            'email' => $ctx['email'] ?? null,
            'customer_id' => $ctx['customer_id'] ?? null,
            'coupon' => $ctx['coupon'] ?? null,
        ]);
        foreach ($promo['line_discounts'] as $key => $d) {
            if (isset($items[$key])) {
                $items[$key]['discount'] = $d;
            }
        }
        $discount = Money::round(array_sum(array_column($items, 'discount')));
        $merchandise = Money::round($subtotal - $discount);
        $weight = round(array_sum(array_column($items, 'weight')), 3);

        // Envío
        $shippingOptions = [];
        $shipping = null;
        if (!empty($ctx['country']) && $items) {
            $shippingOptions = $this->shipping->options((string) $ctx['country'], (string) ($ctx['postcode'] ?? ''), $weight, $merchandise, $groupId);
            if ($promo['free_shipping']) {
                foreach ($shippingOptions as &$o) {
                    $o['price'] = 0.0;
                }
                unset($o);
            }
            foreach ($shippingOptions as $o) {
                if ($o['id'] === (int) ($ctx['shipping_id'] ?? 0)) {
                    $shipping = $o;
                }
            }
            $shipping ??= $shippingOptions[0] ?? null;
        }
        $shippingCost = $shipping['price'] ?? 0.0;

        // Pago
        $beforeFee = Money::round($merchandise + $shippingCost);
        $paymentOptions = $items ? $this->payments->available($beforeFee, $groupId) : [];
        $payment = null;
        foreach ($paymentOptions as $o) {
            if ($o['id'] === (int) ($ctx['payment_id'] ?? 0)) {
                $payment = $o;
            }
        }
        $payment ??= $paymentOptions[0] ?? null;
        $fee = $payment['fee'] ?? 0.0;
        $total = Money::round($beforeFee + $fee);

        // IVA incluido, por tipo. Portes y recargo al tipo de config 'shop.shipping_tax_rate'.
        $byRate = [];
        foreach ($items as $i) {
            $byRate[(string) $i['tax_rate']] = ($byRate[(string) $i['tax_rate']] ?? 0) + $i['total'] - $i['discount'];
        }
        $extraRate = (string) (float) ShopConfig::get('shop.shipping_tax_rate', 21);
        if ($shippingCost + $fee > 0) {
            $byRate[$extraRate] = ($byRate[$extraRate] ?? 0) + $shippingCost + $fee;
        }
        $taxes = [];
        foreach ($byRate as $rate => $gross) {
            $tax = Money::includedTax((float) $gross, (float) $rate);
            $taxes[] = ['rate' => (float) $rate, 'base' => Money::round($gross - $tax), 'tax' => $tax];
        }
        usort($taxes, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        return [
            'items' => array_values($items),
            'lines' => array_map(fn ($l) => ['product_id' => $l['product_id'], 'color' => $l['color'], 'qty' => $l['qty']], $clean),
            'count' => array_sum(array_column($items, 'qty')),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'promotions' => $promo['applied'],
            'free_shipping' => $promo['free_shipping'],
            'coupon' => $ctx['coupon'] ?? null,
            'coupon_error' => $promo['coupon_error'],
            'weight' => $weight,
            'shipping_options' => $shippingOptions,
            'shipping' => $shipping,
            'shipping_cost' => $shippingCost,
            'payment_options' => $paymentOptions,
            'payment' => $payment,
            'payment_fee' => $fee,
            'taxes' => $taxes,
            'tax_total' => Money::round(array_sum(array_column($taxes, 'tax'))),
            'total' => $total,
            'notices' => $notices,
        ];
    }
}
