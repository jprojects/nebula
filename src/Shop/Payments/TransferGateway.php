<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

/**
 * Transferencia bancaria: el pedido queda pendiente y el cliente ve las instrucciones de la forma de pago
 * (con el número de pedido y el importe). Se marca como pagado desde la gestión.
 */
final class TransferGateway extends Gateway
{
    public function online(): bool
    {
        return false;
    }

    public function start(array $order, array $payment, PaymentUrls $urls): PaymentAction
    {
        return PaymentAction::offline();
    }
}
