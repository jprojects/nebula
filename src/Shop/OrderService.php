<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;
use Nebula\Core\Log;

/**
 * Pedidos: creación desde un carrito valorado, estados, existencias y avisos al cliente.
 *
 * Estados: pending (pendiente de pago) → paid → processing → shipped → delivered; cancelled y refunded en
 * cualquier momento. Las existencias se descuentan al crear el pedido y se devuelven al cancelarlo o
 * reembolsarlo (una sola vez; si se reactiva, se vuelven a descontar).
 */
final class OrderService
{
    public const STATUSES = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    /** Estados de los que se avisa al cliente por correo al cambiar desde la gestión */
    public const NOTIFY = ['processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    private const RELEASED = ['cancelled', 'refunded'];

    public function __construct(
        private readonly Database $db,
        private readonly ShopMailer $mailer,
        private readonly Promotions $promotions,
    ) {
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public function findByNumber(string $number): ?array
    {
        return $this->db->fetch('SELECT * FROM orders WHERE number = ?', [$number]);
    }

    public function items(int $orderId): array
    {
        return $this->db->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public function history(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT h.*, u.name AS user_name FROM order_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.order_id = ? ORDER BY h.id',
            [$orderId]
        );
    }

    /**
     * Crea el pedido a partir de un carrito ya valorado (CartCalculator::quote con envío y pago elegidos).
     *
     * @param array $quote resultado de CartCalculator::quote
     * @param array $data datos validados: email, phone, bill_*, ship_*, notes, locale, ip
     * @throws ShopException 'out_of_stock' si otro cliente se ha llevado las últimas unidades
     */
    public function create(array $quote, array $data, array $group, ?int $customerId, array $paymentMethod): array
    {
        $orderId = $this->db->transaction(function () use ($quote, $data, $group, $customerId, $paymentMethod): int {
            $token = bin2hex(random_bytes(16));
            $id = (int) $this->db->insert('orders', [
                'number' => 'tmp-' . substr($token, 0, 24),
                'token' => $token,
                'customer_id' => $customerId,
                'group_id' => (int) $group['id'] ?: null,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method_id' => (int) $paymentMethod['id'],
                'payment_gateway' => (string) $paymentMethod['gateway'],
                'payment_name' => (string) $paymentMethod['name'],
                'shipping_method_id' => $quote['shipping']['id'] ?? null,
                'shipping_name' => $quote['shipping']['name'] ?? null,
                'subtotal' => $quote['subtotal'],
                'discount' => $quote['discount'],
                'shipping_cost' => $quote['shipping_cost'],
                'payment_fee' => $quote['payment_fee'],
                'tax_total' => $quote['tax_total'],
                'total' => $quote['total'],
                'weight' => $quote['weight'],
                'coupon_code' => array_values(array_filter(array_column($quote['promotions'], 'code')))[0] ?? null,
                'promotions' => json_encode($quote['promotions'], JSON_UNESCAPED_UNICODE),
                'taxes' => json_encode($quote['taxes']),
            ] + $data);

            $number = (string) ShopConfig::get('shop.order_prefix', '') . date('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            $this->db->update('orders', ['number' => $number], 'id', $id);

            foreach ($quote['items'] as $item) {
                $this->db->insert('order_items', [
                    'order_id' => $id,
                    'product_id' => $item['product_id'],
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'color_code' => $item['color_code'],
                    'color_name' => $item['color_name'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'tax_rate' => $item['tax_rate'],
                    'total' => Money::round($item['total'] - $item['discount']),
                    'weight' => $item['weight'],
                ]);
                $this->takeStock((int) $item['product_id'], (int) $item['qty']);
            }
            $this->promotions->recordUses(
                array_values(array_filter($quote['promotions'], fn ($p) => $p['amount'] > 0 || $p['type'] === 'free_shipping')),
                $id,
                $customerId,
                (string) $data['email']
            );
            $this->db->insert('order_history', ['order_id' => $id, 'status' => 'pending', 'note' => null]);
            return $id;
        });

        $order = (array) $this->find($orderId);
        Log::channel('shop')->info('Pedido {number} creado ({total})', ['number' => $order['number'], 'total' => $order['total']]);
        return $order;
    }

    /** El pago se ha confirmado (pasarela o gestión). Idempotente. */
    public function markPaid(int $orderId, ?string $note = null, ?int $userId = null): void
    {
        $order = $this->find($orderId);
        if ($order === null || $order['payment_status'] === 'paid') {
            return;
        }
        $update = ['payment_status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')];
        if ($order['status'] === 'pending' || $order['status'] === 'cancelled') {
            $update['status'] = 'paid';
            if ($order['status'] === 'cancelled' && $order['stock_restored']) {
                // Pago que llega tarde a un pedido cancelado por caducado: se vuelven a reservar las unidades
                $this->takeOrderStock($orderId);
                $update['stock_restored'] = 0;
            }
        }
        $this->db->update('orders', $update, 'id', $orderId);
        $this->db->insert('order_history', ['order_id' => $orderId, 'status' => $update['status'] ?? $order['status'],
            'note' => $note, 'user_id' => $userId, 'notified' => 1]);
        Log::channel('shop')->info('Pedido {number} pagado', ['number' => $order['number']]);
        $this->mailer->orderPaid((array) $this->find($orderId));
    }

    /**
     * Cambio de estado. Desde la gestión lo llama OrdersHooks después de guardar (el estado ya está en la fila:
     * $alreadySaved = true); desde la tienda y el cron, con $alreadySaved = false.
     */
    public function changeStatus(int $orderId, string $from, string $to, ?string $note, ?int $userId, bool $notify, bool $alreadySaved = false): void
    {
        if (!in_array($to, self::STATUSES, true) || $from === $to) {
            return;
        }
        $order = $this->find($orderId);
        if ($order === null) {
            return;
        }
        $update = $alreadySaved ? [] : ['status' => $to];
        if ($to === 'shipped' && $order['shipped_at'] === null) {
            $update['shipped_at'] = date('Y-m-d H:i:s');
        }
        if ($to === 'paid' && $order['payment_status'] !== 'paid') {
            $update['payment_status'] = 'paid';
            $update['paid_at'] = date('Y-m-d H:i:s');
        }
        if ($to === 'refunded' && $order['payment_status'] === 'paid') {
            $update['payment_status'] = 'refunded';
        }
        // Existencias
        if (in_array($to, self::RELEASED, true) && !$order['stock_restored']) {
            $this->releaseOrderStock($orderId);
            $update['stock_restored'] = 1;
        } elseif (!in_array($to, self::RELEASED, true) && $order['stock_restored']) {
            $this->takeOrderStock($orderId);
            $update['stock_restored'] = 0;
        }
        if ($update) {
            $this->db->update('orders', $update, 'id', $orderId);
        }
        $sent = false;
        if ($notify && in_array($to, self::NOTIFY, true)) {
            $sent = $this->mailer->statusChanged((array) $this->find($orderId), $to, $note);
        }
        $this->db->insert('order_history', ['order_id' => $orderId, 'status' => $to, 'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
            'user_id' => $userId, 'notified' => $sent ? 1 : 0]);
        Log::channel('shop')->info('Pedido {number}: {from} → {to}', ['number' => $order['number'], 'from' => $from, 'to' => $to]);
    }

    /**
     * Cancela los pedidos sin pagar más antiguos que el plazo de su forma de pago (bin/cron.php).
     * @return int pedidos cancelados
     */
    public function expirePending(int $onlineHours, int $transferDays): int
    {
        $n = 0;
        $rules = [];
        if ($onlineHours > 0) {
            $rules[] = ["payment_gateway <> 'transfer'", date('Y-m-d H:i:s', time() - $onlineHours * 3600)];
        }
        if ($transferDays > 0) {
            $rules[] = ["payment_gateway = 'transfer'", date('Y-m-d H:i:s', time() - $transferDays * 86400)];
        }
        foreach ($rules as [$cond, $before]) {
            foreach ($this->db->fetchAll("SELECT id FROM orders WHERE status = 'pending' AND payment_status <> 'paid' AND $cond AND created_at < ?", [$before]) as $r) {
                $this->changeStatus((int) $r['id'], 'pending', 'cancelled', 'Sin pagar en el plazo', null, false);
                $n++;
            }
        }
        return $n;
    }

    /** Instrucciones de la forma de pago con {number}, {total} y {shop} sustituidos. */
    public static function instructions(array $method, array $order): ?string
    {
        $text = trim((string) ($method['instructions'] ?? ''));
        if ($text === '') {
            return null;
        }
        return strtr($text, [
            '{number}' => (string) $order['number'],
            '{total}' => Money::format((float) $order['total'], $order['locale'] ?? null),
            '{shop}' => ShopConfig::shopName(),
        ]);
    }

    // ---------------------------------------------------------------- existencias

    private function takeStock(int $productId, int $qty): void
    {
        $stock = $this->db->fetchValue('SELECT stock FROM products WHERE id = ? FOR UPDATE', [$productId]);
        if ($stock === null) {
            return; // sin control de existencias (o producto borrado)
        }
        $done = $this->db->query('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?', [$qty, $productId, $qty])->rowCount();
        if ($done === 0) {
            throw new ShopException('out_of_stock');
        }
    }

    /** Vuelve a descontar las unidades de un pedido reactivado (aunque queden en negativo: el pedido ya existe). */
    private function takeOrderStock(int $orderId): void
    {
        foreach ($this->items($orderId) as $i) {
            if ($i['product_id'] !== null) {
                $this->db->query('UPDATE products SET stock = stock - ? WHERE id = ? AND stock IS NOT NULL', [(int) $i['qty'], (int) $i['product_id']]);
            }
        }
    }

    private function releaseOrderStock(int $orderId): void
    {
        foreach ($this->items($orderId) as $i) {
            if ($i['product_id'] !== null) {
                $this->db->query('UPDATE products SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [(int) $i['qty'], (int) $i['product_id']]);
            }
        }
    }
}
