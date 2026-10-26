<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\Action;
use Nebula\Core\ActionResult;
use Nebula\Core\ModuleHooks;
use Nebula\Core\User;
use Nebula\Shop\OrderService;
use Nebula\Shop\Services;

/**
 * Pedidos en la gestión. Un cambio de estado pasa por OrderService (historial, existencias y correo al cliente
 * salvo que se marque «No avisar al cliente»). Las líneas, los pagos y el historial se ven en la ficha (campos "display").
 */
final class OrdersHooks extends ModuleHooks
{
    private ?string $previousStatus = null;
    private ?Services $shop = null;

    public function beforeSave(array &$data, ?string $id, ?array $existing, User $user): ?string
    {
        $this->previousStatus = $existing['status'] ?? null;
        if (isset($data['status']) && !in_array($data['status'], OrderService::STATUSES, true)) {
            return 'validation.status';
        }
        return null;
    }

    public function afterSave(string $id, array $data, bool $isNew, User $user): void
    {
        $to = $data['status'] ?? null;
        if ($to !== null && $this->previousStatus !== null && $to !== $this->previousStatus) {
            $this->shop()->orders()->changeStatus(
                (int) $id,
                $this->previousStatus,
                $to,
                trim((string) ($this->values['status_note'] ?? '')) ?: null,
                $user->id(),
                empty($this->values['silent']),
                true
            );
        }
    }

    /** Un pedido borrado sin cancelar devuelve sus unidades. */
    public function beforeDelete(string $id, User $user): ?string
    {
        $order = $this->db->fetch('SELECT status, stock_restored FROM orders WHERE id = ?', [$id]);
        if ($order !== null && !$order['stock_restored']) {
            foreach ($this->db->fetchAll('SELECT product_id, qty FROM order_items WHERE order_id = ? AND product_id IS NOT NULL', [$id]) as $i) {
                $this->db->query('UPDATE products SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [$i['qty'], $i['product_id']]);
            }
        }
        return null;
    }

    // ---------------------------------------------------------------- bloques de la ficha

    public function orderLines(array $row): array
    {
        return [
            'items' => $this->db->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$row['id']]),
            'order' => $row,
        ];
    }

    public function orderPayments(array $row): array
    {
        return $this->db->fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$row['id']]);
    }

    public function orderHistory(array $row): array
    {
        return $this->shop()->orders()->history((int) $row['id']);
    }

    // ---------------------------------------------------------------- acciones

    /** Transferencia recibida (o cobro fuera de la tienda): pagado, con el correo de confirmación al cliente. */
    public function markPaid(Action $a): ActionResult
    {
        $this->shop()->orders()->markPaid((int) $a->id, $a->t('history.marked_paid'), $a->user->id());
        return ActionResult::success($a->t('flash.marked_paid'));
    }

    public function resendConfirmation(Action $a): ActionResult
    {
        $order = $this->shop()->orders()->find((int) $a->id);
        if ($order === null) {
            return ActionResult::error($a->t('flash.not_found'));
        }
        $order['payment_status'] === 'paid' ? $this->shop()->mailer()->orderPaid($order) : $this->shop()->mailer()->orderPlaced($order);
        return ActionResult::success($a->t('flash.resent', ['email' => $order['email']]));
    }

    public function viewInShop(Action $a): ActionResult
    {
        $order = $this->shop()->orders()->find((int) $a->id);
        return $order === null
            ? ActionResult::error($a->t('flash.not_found'))
            : ActionResult::redirect($this->shop()->urls()->order((string) $order['number'], (string) $order['token'], true));
    }

    private function shop(): Services
    {
        return $this->shop ??= new Services($this->db);
    }
}
