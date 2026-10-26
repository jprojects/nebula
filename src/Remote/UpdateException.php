<?php
declare(strict_types=1);

namespace Nebula\Remote;

/** Error esperado de una actualización: el mensaje y los datos se devuelven tal cual a Cosmo. */
final class UpdateException extends \RuntimeException
{
    public function __construct(string $message, public readonly array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
