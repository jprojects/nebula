<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

use Nebula\Core\Database;
use Nebula\Core\Log;
use Nebula\Shop\Money;
use Nebula\Shop\OrderService;
use Nebula\Shop\ShopConfig;
use Nebula\Shop\Urls;

/**
 * Intentos de pago (tabla payments) y aplicación del resultado de la pasarela al pedido.
 * El resultado se aplica una sola vez aunque lleguen a la vez la notificación y la vuelta del cliente.
 */
final class PaymentService
{
    public function __construct(
        private readonly Database $db,
        private readonly PaymentMethods $methods,
        private readonly OrderService $orders,
        private readonly Urls $urls,
    ) {
    }

    /**
     * Nuevo intento de pago de un pedido pendiente.
     * @return array{0:PaymentAction,1:?array} acción y fila del pago (null si es fuera de línea)
     */
    public function begin(array $order, string $locale): array
    {
        $gateway = $this->methods->gateway((string) $order['payment_gateway']);
        if ($gateway === null || !$gateway->available()) {
            return [PaymentAction::error('Pasarela no disponible: ' . $order['payment_gateway']), null];
        }
        if (!$gateway->online()) {
            return [PaymentAction::offline(), null];
        }
        $id = (int) $this->db->insert('payments', [
            'order_id' => (int) $order['id'],
            'gateway' => $gateway->code,
            'reference' => 'new-' . bin2hex(random_bytes(8)),
            'amount' => (float) $order['total'],
            'status' => 'pending',
        ]);
        $reference = $gateway->reference($id);
        $this->db->update('payments', ['reference' => $reference], 'id', $id);
        $payment = (array) $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$id]);

        $code = rawurlencode($gateway->code);
        $query = ['p' => $id, 't' => $order['token']];
        $urls = new PaymentUrls(
            return: $this->urls->abs("/pay/$code/return", $query),
            cancel: $this->urls->abs("/pay/$code/cancel", $query),
            notify: $this->urls->abs("/pay/$code/notify"),
            order: $this->urls->order((string) $order['number'], (string) $order['token'], true),
            locale: $locale,
            shopName: ShopConfig::shopName(),
        );
        $action = $gateway->start($order, $payment, $urls);
        if ($action->reference !== null) {
            $this->db->update('payments', ['reference' => $action->reference], 'id', $id);
        }
        if ($action->type === 'error') {
            $this->db->update('payments', ['status' => 'failed', 'message' => mb_substr((string) $action->message, 0, 255)], 'id', $id);
            Log::channel('payments')->error('No se pudo iniciar el pago del pedido {number}: {message}', ['number' => $order['number'], 'message' => $action->message]);
        }
        return [$action, $payment];
    }

    /** Aviso servidor a servidor. Devuelve si se ha aceptado. */
    public function notify(string $gatewayCode, array $get, array $post, string $body): bool
    {
        $gateway = $this->methods->gateway($gatewayCode);
        $result = $gateway?->notify($get, $post, $body);
        if ($gateway === null || $result === null) {
            Log::channel('payments')->warning('Notificación de pago no válida ({gateway})', ['gateway' => $gatewayCode, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
            return false;
        }
        $payment = $this->db->fetch('SELECT * FROM payments WHERE gateway = ? AND reference = ?', [$gateway->code, $result->reference]);
        if ($payment === null) {
            Log::channel('payments')->warning('Notificación de un pago desconocido {reference}', ['reference' => $result->reference]);
            return false;
        }
        $this->apply($payment, $result);
        return true;
    }

    /**
     * El cliente vuelve de la pasarela.
     * @return ?array el pago actualizado (null si no existe o el token no es el del pedido)
     */
    public function complete(string $gatewayCode, int $paymentId, string $token, array $get, array $post): ?array
    {
        $payment = $this->paymentForOrderToken($paymentId, $token, $gatewayCode);
        $gateway = $this->methods->gateway($gatewayCode);
        if ($payment === null || $gateway === null) {
            return null;
        }
        if ($payment['status'] === 'pending') {
            $this->apply($payment, $gateway->complete($payment, $get, $post));
        }
        return $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    }

    /** El cliente ha cancelado en la pasarela. */
    public function cancel(string $gatewayCode, int $paymentId, string $token): ?array
    {
        $payment = $this->paymentForOrderToken($paymentId, $token, $gatewayCode);
        if ($payment === null) {
            return null;
        }
        $gateway = $this->methods->gateway($gatewayCode);
        if ($payment['status'] === 'pending' && $gateway !== null) {
            // Redsys también vuelve a URLKO con el resultado firmado: si lo trae, se usa
            $result = $gateway->complete($payment, $_GET, $_POST);
            $this->apply($payment, $result->status === 'pending' ? PaymentResult::cancelled((string) $payment['reference']) : $result);
        }
        return $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    }

    private function paymentForOrderToken(int $paymentId, string $token, string $gatewayCode): ?array
    {
        $payment = $this->db->fetch(
            'SELECT p.*, o.token AS order_token FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.id = ? AND p.gateway = ?',
            [$paymentId, $gatewayCode]
        );
        return $payment !== null && hash_equals((string) $payment['order_token'], $token) ? $payment : null;
    }

    private function apply(array $payment, PaymentResult $result): void
    {
        if ($result->status === 'pending') {
            return;
        }
        // Bloqueo de la fila: la notificación y la vuelta pueden llegar a la vez
        $paid = $this->db->transaction(function () use ($payment, $result): bool {
            $current = $this->db->fetch('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$payment['id']]);
            if ($current === null || $current['status'] === 'paid') {
                return false;
            }
            $status = $result->status;
            $message = $result->message;
            if ($status === 'paid' && $result->amount !== null && Money::cents($result->amount) !== Money::cents((float) $current['amount'])) {
                $status = 'failed';
                $message = 'Importe cobrado distinto del pedido: ' . $result->amount;
                Log::channel('payments')->critical('Pago {id}: importe distinto ({amount} en lugar de {expected})',
                    ['id' => $current['id'], 'amount' => $result->amount, 'expected' => $current['amount']]);
            }
            $this->db->update('payments', [
                'status' => $status,
                'transaction_id' => $result->transactionId !== null ? mb_substr($result->transactionId, 0, 100) : null,
                'message' => $message !== null ? mb_substr($message, 0, 255) : null,
                'response' => json_encode($result->data, JSON_UNESCAPED_UNICODE),
            ], 'id', $current['id']);
            return $status === 'paid';
        });
        Log::channel('payments')->info('Pago {id} ({gateway}): {status}', ['id' => $payment['id'], 'gateway' => $payment['gateway'], 'status' => $result->status]);
        if ($paid) {
            $this->orders->markPaid((int) $payment['order_id'], 'Pago ' . $payment['gateway'] . ' ' . ($result->transactionId ?? ''));
        }
    }
}
