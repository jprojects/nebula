<?php
declare(strict_types=1);

namespace Nebula\Shop;

/**
 * Error esperado de la tienda que se muestra al cliente. El mensaje es una clave de lang/{idioma}/store.json
 * dentro de "errors" (p. ej. 'out_of_stock' → errors.out_of_stock).
 */
final class ShopException extends \RuntimeException
{
    public function __construct(string $key, public readonly array $vars = [], public readonly int $status = 422)
    {
        parent::__construct($key);
    }
}
