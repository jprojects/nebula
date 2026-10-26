<?php
declare(strict_types=1);

namespace Nebula\Pages;

use Nebula\Controllers\Page;
use Nebula\Shop\ShopConfig;

/**
 * Panel de la tienda: ventas de hoy, del mes y de los últimos 30 días, pedidos por gestionar,
 * pedidos recientes, productos más vendidos y existencias bajas.
 */
final class DashboardPage extends Page
{
    public function index(): void
    {
        $db = $this->app->db;
        $paid = "payment_status = 'paid' AND status NOT IN ('cancelled', 'refunded')";
        $sum = fn (string $from, ?string $to = null) => $db->fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total FROM orders WHERE $paid AND COALESCE(paid_at, created_at) >= ?"
                . ($to !== null ? ' AND COALESCE(paid_at, created_at) < ?' : ''),
            $to !== null ? [$from, $to] : [$from]
        );
        $today = $sum(date('Y-m-d 00:00:00'));
        $month = $sum(date('Y-m-01 00:00:00'));
        $prevMonth = $sum(date('Y-m-01 00:00:00', strtotime('first day of last month')), date('Y-m-01 00:00:00'));

        $days = [];
        foreach ($db->fetchAll(
            "SELECT DATE(COALESCE(paid_at, created_at)) AS d, SUM(total) AS total FROM orders WHERE $paid AND COALESCE(paid_at, created_at) >= ? GROUP BY d",
            [date('Y-m-d', strtotime('-29 days'))]
        ) as $r) {
            $days[$r['d']] = (float) $r['total'];
        }
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $series[] = ['date' => $d, 'total' => $days[$d] ?? 0.0];
        }

        $this->view([
            'kpis' => [
                'today' => $today,
                'month' => $month,
                'prev_month' => $prevMonth,
                'pending_payment' => (int) $db->fetchValue("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
                'to_ship' => (int) $db->fetchValue("SELECT COUNT(*) FROM orders WHERE status IN ('paid', 'processing')"),
                'customers' => (int) $db->fetchValue('SELECT COUNT(*) FROM customers WHERE created_at >= ?', [date('Y-m-01 00:00:00')]),
            ],
            'series' => $series,
            'recent' => $db->fetchAll('SELECT id, number, bill_name, status, payment_status, total, created_at FROM orders ORDER BY id DESC LIMIT 8'),
            'top' => $db->fetchAll(
                "SELECT i.product_id, i.name, SUM(i.qty) AS qty, SUM(i.total) AS total FROM order_items i JOIN orders o ON o.id = i.order_id
                 WHERE o.$paid AND o.created_at >= ? GROUP BY i.product_id, i.name ORDER BY qty DESC LIMIT 6",
                [date('Y-m-d', strtotime('-30 days'))]
            ),
            'lowStock' => $db->fetchAll(
                'SELECT id, name, sku, stock FROM products WHERE active = 1 AND stock IS NOT NULL AND stock <= ? ORDER BY stock, name LIMIT 8',
                [(int) ShopConfig::get('shop.low_stock', 3)]
            ),
            'canOrders' => ($m = $this->app->modules()->get('orders')) !== null && $this->allowed('view', $m),
            'canProducts' => ($m = $this->app->modules()->get('products')) !== null && $this->allowed('view', $m),
            'shopUrl' => \Nebula\Shop\Urls::fromConfig()->abs('/'),
        ]);
    }
}
