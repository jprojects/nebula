<?php
declare(strict_types=1);

namespace Nebula\Shop;

/**
 * Carrito del visitante, en la sesión de la tienda: solo producto, color y cantidad. Los precios se calculan
 * siempre de nuevo con CartCalculator (el cliente nunca envía importes).
 */
final class Cart
{
    private const KEY = 'nb_cart';
    public const MAX_QTY = 999;
    public const MAX_LINES = 100;

    public static function key(int $productId, ?string $color): string
    {
        return $productId . ':' . ($color ?? '');
    }

    /** @return array<string,array{product_id:int,color:?string,qty:int}> */
    public function lines(): array
    {
        $lines = $_SESSION[self::KEY]['lines'] ?? [];
        return is_array($lines) ? $lines : [];
    }

    public function count(): int
    {
        return array_sum(array_column($this->lines(), 'qty'));
    }

    public function isEmpty(): bool
    {
        return !$this->lines();
    }

    public function add(int $productId, ?string $color, int $qty): string
    {
        $key = self::key($productId, $color);
        $lines = $this->lines();
        if (!isset($lines[$key]) && count($lines) >= self::MAX_LINES) {
            throw new \RangeException('cart_full');
        }
        $current = $lines[$key]['qty'] ?? 0;
        $lines[$key] = ['product_id' => $productId, 'color' => $color, 'qty' => max(1, min(self::MAX_QTY, $current + $qty))];
        $this->save($lines);
        return $key;
    }

    public function set(string $key, int $qty): void
    {
        $lines = $this->lines();
        if (!isset($lines[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($lines[$key]);
        } else {
            $lines[$key]['qty'] = min(self::MAX_QTY, $qty);
        }
        $this->save($lines);
    }

    public function remove(string $key): void
    {
        $this->set($key, 0);
    }

    /** Sustituye las líneas (el calculador ajusta cantidades a las existencias o quita productos retirados). */
    public function replace(array $lines): void
    {
        $this->save($lines);
    }

    public function coupon(): ?string
    {
        $c = $_SESSION[self::KEY]['coupon'] ?? null;
        return is_string($c) && $c !== '' ? $c : null;
    }

    public function setCoupon(?string $code): void
    {
        $_SESSION[self::KEY]['coupon'] = $code !== null && trim($code) !== '' ? mb_substr(trim($code), 0, 40) : null;
    }

    public function clear(): void
    {
        unset($_SESSION[self::KEY]);
    }

    private function save(array $lines): void
    {
        $_SESSION[self::KEY]['lines'] = $lines;
    }
}
