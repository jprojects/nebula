<?php
declare(strict_types=1);

namespace Nebula\Shop\Controllers;

use Nebula\Core\HttpException;
use Nebula\Core\Log;
use Nebula\Core\Session;
use Nebula\Shop\Money;
use Nebula\Shop\OrderService;
use Nebula\Shop\ShopConfig;
use Nebula\Shop\ShopException;

/**
 * Checkout en una sola página: cada cambio de dirección, envío o pago pide /api/checkout/quote y se repintan
 * las opciones y los totales sin recargar. Al confirmar, /api/checkout/place vuelve a calcularlo todo, crea el
 * pedido y devuelve a dónde ir (pasarela o página del pedido).
 */
final class CheckoutController extends ShopController
{
    private const ADDRESS = ['name' => 150, 'address' => 255, 'city' => 100, 'postcode' => 20, 'province' => 100, 'country' => 2];

    public function page(): void
    {
        $cart = $this->shop->cart();
        if ($cart->isEmpty()) {
            $this->shop->redirect($this->view->url('/carrito'));
        }
        $customer = $this->shop->customer();
        $country = strtoupper((string) ShopConfig::get('shop.default_country', 'ES'));
        $addresses = $customer !== null ? $this->services->customers()->addresses($customer) : [];
        $initial = $addresses[0] ?? [];
        $quote = $this->quoteFor([
            'country' => $initial['country'] ?? $country,
            'postcode' => $initial['postcode'] ?? '',
        ]);
        $this->shop->html('checkout', [
            'quote' => $quote,
            'countries' => $this->db->fetchAll('SELECT code, name FROM countries WHERE active = 1 ORDER BY sort, name'),
            'addresses' => $addresses,
            'initial' => $initial + ['country' => $country],
            'guestAllowed' => (bool) ShopConfig::get('shop.guest_checkout', true),
        ] + $this->meta($this->t('checkout.title')));
    }

    public function quote(): void
    {
        $quote = $this->quoteFor($this->destination());
        $this->shop->json(['ok' => true] + $this->payload($quote));
    }

    public function place(): void
    {
        $in = $this->shop->input();
        $customer = $this->shop->customer();
        $errors = [];
        $data = $this->validate($in, $customer, $errors);

        $dest = $this->destination();
        $quote = $this->quoteFor($dest);
        if (!$quote['items']) {
            throw new ShopException('cart_empty');
        }
        $shipping = $quote['shipping'];
        if ($shipping === null) {
            $errors['shipping_id'] = $this->t('errors.no_shipping');
        } elseif ((int) ($in['shipping_id'] ?? 0) !== $shipping['id']) {
            $errors['shipping_id'] = $this->t('errors.choose_shipping');
        }
        $payment = $quote['payment'];
        if ($payment === null || (int) ($in['payment_id'] ?? 0) !== $payment['id']) {
            $errors['payment_id'] = $this->t('errors.choose_payment');
        }
        if ($shipping !== null && $shipping['pickup']) {
            foreach (array_keys(self::ADDRESS) as $k) {
                $data['ship_' . $k] = null;
                unset($errors['ship_' . $k]);
            }
        }
        if ($errors) {
            $this->shop->json(['ok' => false, 'error' => $this->t('errors.check_form'), 'errors' => $errors] + $this->payload($quote), 422);
            return;
        }
        // El cliente confirma lo que ha visto: si el total ha cambiado (precio, existencias, promoción), se le enseña
        if (isset($in['expected_total']) && Money::cents((float) $in['expected_total']) !== Money::cents($quote['total'])) {
            $this->shop->json(['ok' => false, 'error' => $this->t('errors.total_changed')] + $this->payload($quote), 409);
            return;
        }

        $group = $this->shop->group();
        $customers = $this->services->customers();
        $locale = $this->view->locale();
        if ($customer === null) {
            if (!empty($in['create_account'])) {
                $customer = $customers->register([
                    'email' => $data['email'], 'name' => $data['bill_name'], 'password' => (string) $in['password'],
                    'phone' => $data['phone'], 'company' => $data['bill_company'], 'vat_id' => $data['bill_vat_id'],
                    'accepts_marketing' => !empty($in['accepts_marketing']),
                ], (int) $group['id'], $locale);
            } elseif (!ShopConfig::get('shop.guest_checkout', true)) {
                throw new ShopException('login_required', [], 401);
            }
        }
        $customerId = $customer !== null
            ? (int) $customer['id']
            : $customers->guest($data['email'], $data['bill_name'], $data['phone'], (int) $group['id'], $locale);

        $method = $this->services->paymentMethods()->find($payment['id']) ?? throw new ShopException('choose_payment');
        $order = $this->services->orders()->create($quote, $data + [
            'locale' => $locale,
            'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ], $group, $customerId, $method);

        if ($customer !== null) {
            $customers->rememberAddress((int) $customer['id'], $this->addressFrom($data, $shipping['pickup'] || empty($data['ship_address']) ? 'bill_' : 'ship_'));
        }
        $this->shop->cart()->clear();
        Session::set('nb_last_order', $order['number']);

        $gateway = $this->services->paymentMethods()->gateway((string) $order['payment_gateway']);
        if ($gateway !== null && !$gateway->online()) {
            $this->services->mailer()->orderPlaced($order);
            $redirect = $this->view->urls->order((string) $order['number'], (string) $order['token']);
        } else {
            $redirect = $this->view->url('/pedido/' . rawurlencode((string) $order['number']) . '/pagar', ['t' => $order['token']]);
        }
        $this->shop->json(['ok' => true, 'redirect' => $redirect, 'number' => $order['number']]);
    }

    /** Página del pedido (gracias, instrucciones de pago, estado). Con AJAX, solo el estado (para esperar al pago). */
    public function order(string $number): void
    {
        $order = $this->accessibleOrder($number);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->shop->json(['ok' => true, 'status' => $order['status'], 'payment_status' => $order['payment_status']]);
            return;
        }
        $orders = $this->services->orders();
        $method = $order['payment_method_id'] ? $this->services->paymentMethods()->find((int) $order['payment_method_id']) : null;
        $gateway = $this->services->paymentMethods()->gateway((string) $order['payment_gateway']);
        $lastPayment = $this->db->fetch('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$order['id']]);
        $this->shop->html('order', [
            'order' => $order,
            'items' => $orders->items((int) $order['id']),
            'history' => $orders->history((int) $order['id']),
            'instructions' => $method ? OrderService::instructions($method, $order) : null,
            'canPay' => $order['status'] === 'pending' && $order['payment_status'] !== 'paid' && $gateway !== null && $gateway->online() && $gateway->available(),
            'lastPayment' => $lastPayment,
            'justPlaced' => Session::get('nb_last_order') === $order['number'],
            'flashes' => Session::takeFlashes(),
        ] + $this->meta($this->t('order.title', ['number' => $order['number']])));
    }

    /** Ir a la pasarela (formulario que se envía solo, o redirección). */
    public function pay(string $number): void
    {
        $order = $this->accessibleOrder($number);
        $orderUrl = $this->view->urls->order((string) $order['number'], (string) $order['token']);
        if ($order['status'] !== 'pending' || $order['payment_status'] === 'paid') {
            $this->shop->redirect($orderUrl);
        }
        [$action] = $this->services->payments()->begin($order, $this->view->locale());
        match ($action->type) {
            'redirect' => $this->shop->redirect((string) $action->url),
            'form' => $this->shop->html('pay_redirect', ['action' => $action, 'order' => $order] + $this->meta($this->t('pay.redirecting')), 200, 'blank'),
            'error' => (function () use ($orderUrl): void {
                Session::flash('error', $this->t('errors.payment_start'));
                $this->shop->redirect($orderUrl);
            })(),
            default => $this->shop->redirect($orderUrl),
        };
    }

    public function payReturn(string $gateway): void
    {
        $payment = $this->services->payments()->complete($gateway, (int) $this->query('p'), $this->query('t'), $_GET, $_POST);
        $this->backToOrder($payment, $payment !== null && $payment['status'] === 'failed' ? 'error' : null);
    }

    public function payCancel(string $gateway): void
    {
        $payment = $this->services->payments()->cancel($gateway, (int) $this->query('p'), $this->query('t'));
        $this->backToOrder($payment, $payment !== null && $payment['status'] !== 'paid' ? 'cancelled' : null);
    }

    public function payNotify(string $gateway): void
    {
        $ok = $this->services->payments()->notify($gateway, $_GET, $_POST, (string) file_get_contents('php://input'));
        http_response_code($ok ? 200 : 400);
        header('Content-Type: text/plain; charset=utf-8');
        echo $this->services->paymentMethods()->gateway($gateway)?->notifyResponse($ok) ?? ($ok ? 'OK' : 'KO');
    }

    // ---------------------------------------------------------------- internos

    private function backToOrder(?array $payment, ?string $flash): void
    {
        if ($payment === null) {
            throw new HttpException(404);
        }
        $order = $this->services->orders()->find((int) $payment['order_id']) ?? throw new HttpException(404);
        if ($flash === 'error') {
            Session::flash('error', $this->t('pay.failed'));
        } elseif ($flash === 'cancelled') {
            Session::flash('warning', $this->t('pay.cancelled'));
        }
        $this->shop->redirect($this->view->urls->order((string) $order['number'], (string) $order['token']));
    }

    /** El pedido se ve con su token (enlace del correo) o con la sesión del cliente que lo hizo. */
    private function accessibleOrder(string $number): array
    {
        $order = $this->services->orders()->findByNumber($number) ?? throw new HttpException(404);
        $token = $this->query('t');
        $customer = $this->shop->customer();
        $owner = $customer !== null && (int) $order['customer_id'] === (int) $customer['id'] && $customer['password_hash'] !== null;
        if (!$owner && ($token === '' || !hash_equals((string) $order['token'], $token))) {
            throw new HttpException(404);
        }
        return $order;
    }

    /** País y código postal del envío (o de facturación si se envía a la misma dirección). */
    private function destination(): array
    {
        $in = $this->shop->input();
        $same = !empty($in['ship_same']);
        $prefix = $same ? 'bill_' : 'ship_';
        return [
            'country' => strtoupper(substr(trim((string) ($in[$prefix . 'country'] ?? $in['bill_country'] ?? '')), 0, 2)),
            'postcode' => mb_substr(trim((string) ($in[$prefix . 'postcode'] ?? '')), 0, 20),
            'shipping_id' => (int) ($in['shipping_id'] ?? 0),
            'payment_id' => (int) ($in['payment_id'] ?? 0),
            'email' => trim((string) ($in['email'] ?? '')),
        ];
    }

    private function quoteFor(array $dest): array
    {
        $cart = $this->shop->cart();
        $customer = $this->shop->customer();
        $quote = $this->services->calculator()->quote($cart->lines(), [
            'group' => $this->shop->group(),
            'coupon' => $cart->coupon(),
            'email' => $customer['email'] ?? ($dest['email'] ?? null),
            'customer_id' => $customer !== null ? (int) $customer['id'] : null,
            'country' => $dest['country'] ?? null,
            'postcode' => $dest['postcode'] ?? '',
            'shipping_id' => $dest['shipping_id'] ?? null,
            'payment_id' => $dest['payment_id'] ?? null,
        ]);
        if ($quote['lines'] != $cart->lines()) {
            $cart->replace($quote['lines']);
        }
        return $quote;
    }

    /** Opciones y totales ya pintados para el checkout. */
    private function payload(array $quote): array
    {
        return [
            'shipping_html' => $this->view->partial('partials/checkout_shipping', ['quote' => $quote]),
            'payment_html' => $this->view->partial('partials/checkout_payment', ['quote' => $quote]),
            'summary_html' => $this->view->partial('partials/checkout_summary', ['quote' => $quote]),
            'total' => $quote['total'],
            'pickup' => (bool) ($quote['shipping']['pickup'] ?? false),
            'empty' => !$quote['items'],
        ];
    }

    private function validate(array $in, ?array $customer, array &$errors): array
    {
        $str = fn (string $k, int $max) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);
        $email = $customer !== null ? (string) $customer['email'] : mb_strtolower($str('email', 150));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = $this->t('errors.email');
        }
        $data = ['email' => $email, 'phone' => $str('phone', 30) ?: null];
        foreach (self::ADDRESS as $k => $max) {
            $data['bill_' . $k] = $str('bill_' . $k, $max);
            if ($k !== 'province' && $data['bill_' . $k] === '') {
                $errors['bill_' . $k] = $this->t('errors.required');
            }
        }
        $data['bill_country'] = strtoupper($data['bill_country']);
        $data['bill_company'] = $str('bill_company', 150) ?: null;
        $data['bill_vat_id'] = $str('bill_vat_id', 30) ?: null;
        $data['bill_province'] = $data['bill_province'] ?: null;

        $same = !empty($in['ship_same']);
        foreach (self::ADDRESS as $k => $max) {
            $data['ship_' . $k] = $same ? $data['bill_' . $k] : $str('ship_' . $k, $max);
            if (!$same && $k !== 'province' && $data['ship_' . $k] === '') {
                $errors['ship_' . $k] = $this->t('errors.required');
            }
        }
        $data['ship_country'] = strtoupper((string) $data['ship_country']);
        $data['ship_province'] = $data['ship_province'] ?: null;
        $data['ship_phone'] = $same ? $data['phone'] : ($str('ship_phone', 30) ?: $data['phone']);

        foreach (['bill_country', 'ship_country'] as $k) {
            if ($data[$k] !== '' && !$this->db->fetchValue('SELECT 1 FROM countries WHERE code = ? AND active = 1', [$data[$k]])) {
                $errors[$k] = $this->t('errors.country');
            }
        }
        $data['notes'] = $str('notes', 1000) ?: null;
        if (empty($in['accept_terms'])) {
            $errors['accept_terms'] = $this->t('errors.accept_terms');
        }
        if ($customer === null && !empty($in['create_account']) && mb_strlen((string) ($in['password'] ?? '')) < 8) {
            $errors['password'] = $this->t('errors.password_short');
        }
        if ($errors) {
            Log::channel('shop')->debug('Checkout con errores', ['fields' => array_keys($errors)]);
        }
        return $data;
    }

    private function addressFrom(array $data, string $prefix): array
    {
        $out = [];
        foreach (array_keys(self::ADDRESS) as $k) {
            $out[$k] = (string) ($data[$prefix . $k] ?? '');
        }
        $out['phone'] = (string) ($data[$prefix === 'ship_' ? 'ship_phone' : 'phone'] ?? '');
        return $out;
    }
}
