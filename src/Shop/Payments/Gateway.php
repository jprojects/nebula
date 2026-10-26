<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

/**
 * Pasarela de pago. Para añadir una nueva:
 *
 *   1. Una clase que extienda Gateway (p. ej. src/Shop/Payments/StripeGateway.php).
 *   2. Una entrada en config 'payments.gateways' ('stripe' => Nebula\Shop\Payments\StripeGateway::class)
 *      y sus claves en 'payments.stripe' (config.local.php).
 *   3. En la gestión, Formas de pago → Nueva, con la pasarela "stripe".
 *
 * Flujo: el cliente confirma el pedido → start() devuelve qué hacer (ir a la pasarela, enviarle un formulario
 * o nada si el pago es fuera de línea) → la pasarela avisa al servidor (notify(), por /pay/{código}/notify) y/o
 * devuelve al cliente (complete(), por /pay/{código}/return). PaymentService aplica el resultado una sola vez.
 */
abstract class Gateway
{
    /**
     * @param array<string,mixed> $config config 'payments.{código}'
     * @param string $code código de la pasarela en config 'payments.gateways'
     */
    public function __construct(protected readonly array $config, public readonly string $code)
    {
    }

    /** ¿Está configurada (claves puestas)? Si no, la forma de pago no se ofrece en la tienda. */
    public function available(): bool
    {
        return true;
    }

    /** true = el pago se hace en la pasarela (tarjeta, PayPal…); false = fuera de línea (transferencia, contra reembolso). */
    public function online(): bool
    {
        return true;
    }

    /**
     * Referencia que se envía a la pasarela para este intento de pago. Por defecto el id del pago;
     * la que necesita su propio formato la redefine (Redsys: 4-12 caracteres, los 4 primeros números).
     */
    public function reference(int $paymentId): string
    {
        return (string) $paymentId;
    }

    /**
     * Empieza el pago.
     * @param array $order fila de orders
     * @param array $payment fila de payments (id, reference, amount)
     */
    abstract public function start(array $order, array $payment, PaymentUrls $urls): PaymentAction;

    /**
     * Aviso de la pasarela al servidor (sin sesión del cliente). Devuelve el resultado con la referencia del pago,
     * o null si la petición no es válida (firma incorrecta…).
     */
    public function notify(array $get, array $post, string $body): ?PaymentResult
    {
        return null;
    }

    /** El cliente vuelve de la pasarela. Por defecto se queda como estaba (lo resuelve notify()). */
    public function complete(array $payment, array $get, array $post): PaymentResult
    {
        return PaymentResult::pending((string) $payment['reference']);
    }

    /** Respuesta que espera la pasarela a su aviso. */
    public function notifyResponse(bool $ok): string
    {
        return $ok ? 'OK' : 'KO';
    }

    protected function opt(string $key, mixed $default = null): mixed
    {
        $v = $this->config[$key] ?? null;
        return $v === null || $v === '' ? $default : $v;
    }
}
