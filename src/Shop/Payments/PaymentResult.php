<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

/** Resultado de un pago tal como lo comunica la pasarela. */
final class PaymentResult
{
    /**
     * @param 'paid'|'failed'|'cancelled'|'pending' $status
     * @param ?float $amount importe cobrado según la pasarela (se comprueba contra el del pago)
     */
    private function __construct(
        public readonly string $status,
        public readonly string $reference,
        public readonly ?string $transactionId = null,
        public readonly ?float $amount = null,
        public readonly ?string $message = null,
        public readonly array $data = [],
    ) {
    }

    public static function paid(string $reference, ?string $transactionId, ?float $amount, array $data = []): self
    {
        return new self('paid', $reference, $transactionId, $amount, null, $data);
    }

    public static function failed(string $reference, string $message, array $data = []): self
    {
        return new self('failed', $reference, null, null, $message, $data);
    }

    public static function cancelled(string $reference, array $data = []): self
    {
        return new self('cancelled', $reference, null, null, null, $data);
    }

    public static function pending(string $reference, array $data = []): self
    {
        return new self('pending', $reference, null, null, null, $data);
    }
}
