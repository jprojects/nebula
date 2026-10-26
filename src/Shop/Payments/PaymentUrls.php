<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

/** URLs absolutas que una pasarela necesita para un intento de pago. */
final class PaymentUrls
{
    public function __construct(
        public readonly string $return,   // el cliente vuelve tras pagar (o intentarlo)
        public readonly string $cancel,   // el cliente cancela en la pasarela
        public readonly string $notify,   // aviso servidor a servidor
        public readonly string $order,    // página del pedido
        public readonly string $locale,   // idioma de la tienda (ca, es, en…)
        public readonly string $shopName,
    ) {
    }
}
