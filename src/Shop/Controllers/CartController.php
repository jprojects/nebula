<?php
declare(strict_types=1);

namespace Nebula\Shop\Controllers;

use Nebula\Shop\Cart;
use Nebula\Shop\Catalog;
use Nebula\Shop\ShopException;

/**
 * Carrito: página y API (añadir, cambiar cantidad, quitar, cupón). Cada respuesta de la API trae el carrito
 * ya pintado para el panel lateral y el contador de la cabecera.
 */
final class CartController extends ShopController
{
    public function page(): void
    {
        $quote = $this->shop->cartSummary();
        $this->shop->html('cart', ['quote' => $quote] + $this->meta($this->t('cart.title')));
    }

    public function state(): void
    {
        $this->shop->json(['ok' => true, 'cart' => $this->shop->cartPayload()]);
    }

    public function add(): void
    {
        $productId = (int) $this->input('product_id');
        $color = $this->input('color') !== '' ? $this->input('color') : null;
        $qty = max(1, min(Cart::MAX_QTY, (int) $this->input('qty', '1')));

        $row = $this->services->catalog()->productRow($productId) ?? throw new ShopException('product_unavailable', [], 404);
        if (Catalog::colors($row) && Catalog::color($row, $color) === null) {
            throw new ShopException('choose_color');
        }
        if ($row['stock'] !== null && (int) $row['stock'] <= 0) {
            throw new ShopException('out_of_stock');
        }
        try {
            $this->shop->cart()->add($productId, Catalog::colors($row) ? $color : null, $qty);
        } catch (\RangeException) {
            throw new ShopException('cart_full');
        }
        $this->shop->json(['ok' => true, 'message' => $this->t('cart.added', ['name' => $row['name']]), 'cart' => $this->shop->cartPayload()]);
    }

    /** {key, qty}: qty 0 quita la línea. */
    public function update(): void
    {
        $this->shop->cart()->set($this->input('key'), max(0, (int) $this->input('qty')));
        $this->respond();
    }

    public function coupon(): void
    {
        $code = $this->input('code');
        $cart = $this->shop->cart();
        $cart->setCoupon($code !== '' ? $code : null);
        $quote = $this->shop->cartSummary();
        if ($code !== '' && $quote['coupon_error'] !== null) {
            $cart->setCoupon(null);
            $this->shop->json(['ok' => false, 'error' => $this->t('errors.' . $quote['coupon_error']), 'cart' => $this->shop->cartPayload()], 422);
            return;
        }
        $this->respond($quote, $code !== '' ? $this->t('cart.coupon_applied') : null);
    }

    private function respond(?array $quote = null, ?string $message = null): void
    {
        $quote ??= $this->shop->cartSummary();
        $this->shop->json([
            'ok' => true,
            'message' => $message,
            'cart' => $this->shop->cartPayload($quote),
            // La página del carrito sustituye su contenido entero
            'page' => isset($_SERVER['HTTP_X_CART_PAGE']) ? $this->view->partial('partials/cart_page', ['quote' => $quote]) : null,
        ]);
    }
}
