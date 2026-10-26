<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

/** Lo que hay que hacer con el cliente al empezar un pago. */
final class PaymentAction
{
    /**
     * @param 'redirect'|'form'|'offline'|'error' $type
     * @param array<string,string> $fields campos del formulario (type=form), que se envía solo por POST
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $url = null,
        public readonly array $fields = [],
        public readonly ?string $message = null,
        public readonly ?string $reference = null,
    ) {
    }

    /** La pasarela ha dado su propia referencia al pago (p. ej. el id de la orden de PayPal). */
    public function withReference(string $reference): self
    {
        return new self($this->type, $this->url, $this->fields, $this->message, $reference);
    }

    public static function redirect(string $url): self
    {
        return new self('redirect', $url);
    }

    public static function form(string $url, array $fields): self
    {
        return new self('form', $url, $fields);
    }

    /** Pago fuera de línea: se muestran las instrucciones de la forma de pago. */
    public static function offline(): self
    {
        return new self('offline');
    }

    /** No se ha podido empezar (pasarela caída, claves incorrectas…); el mensaje va al log, no al cliente. */
    public static function error(string $message): self
    {
        return new self('error', null, [], $message);
    }
}
