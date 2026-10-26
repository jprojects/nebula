<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

use Nebula\Core\Log;
use Nebula\Shop\Money;
use Nebula\Shop\ShopConfig;

/**
 * PayPal Checkout (API REST Orders v2) por redirección: se crea la orden en PayPal, el cliente la aprueba
 * allí y, al volver, se captura. config 'payments.paypal': sandbox, client_id, client_secret, brand_name.
 * Las credenciales se crean en developer.paypal.com (Apps & Credentials), una pareja para sandbox y otra para live.
 */
final class PaypalGateway extends Gateway
{
    private const API_LIVE = 'https://api-m.paypal.com';
    private const API_TEST = 'https://api-m.sandbox.paypal.com';
    private const LOCALES = ['es' => 'es-ES', 'ca' => 'es-ES', 'en' => 'en-GB', 'fr' => 'fr-FR', 'de' => 'de-DE', 'it' => 'it-IT', 'pt' => 'pt-PT'];

    public function available(): bool
    {
        return $this->opt('client_id') !== null && $this->opt('client_secret') !== null && function_exists('curl_init');
    }

    /** La referencia definitiva es el id de la orden de PayPal; hasta crearla, una provisional. */
    public function reference(int $paymentId): string
    {
        return 'tmp-' . $paymentId;
    }

    public function start(array $order, array $payment, PaymentUrls $urls): PaymentAction
    {
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order['number'],
                'custom_id' => (string) $payment['id'],
                'invoice_id' => $order['number'] . '-' . $payment['id'],
                'description' => mb_substr($urls->shopName . ' · ' . $order['number'], 0, 127),
                'amount' => ['currency_code' => ShopConfig::currency(), 'value' => Money::plain((float) $payment['amount'])],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => mb_substr((string) $this->opt('brand_name', $urls->shopName), 0, 127),
                'locale' => self::LOCALES[$urls->locale] ?? 'es-ES',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $urls->return,
                'cancel_url' => $urls->cancel,
            ]]],
        ];
        try {
            $res = $this->api('POST', '/v2/checkout/orders', $body, 'nebula-' . $payment['id']);
        } catch (\RuntimeException $e) {
            return PaymentAction::error($e->getMessage());
        }
        foreach ((array) ($res['links'] ?? []) as $link) {
            if (in_array($link['rel'] ?? '', ['payer-action', 'approve'], true) && !empty($res['id'])) {
                return PaymentAction::redirect((string) $link['href'])->withReference((string) $res['id']);
            }
        }
        return PaymentAction::error('PayPal no ha devuelto el enlace de aprobación');
    }

    /** Vuelta de PayPal (?token=ID&PayerID=…): se captura la orden. */
    public function complete(array $payment, array $get, array $post): PaymentResult
    {
        $ref = (string) $payment['reference'];
        $token = is_string($get['token'] ?? null) ? $get['token'] : '';
        if ($token === '' || !hash_equals($ref, $token)) {
            return PaymentResult::pending($ref);
        }
        try {
            $res = $this->api('POST', '/v2/checkout/orders/' . rawurlencode($ref) . '/capture', null, 'capture-' . $ref);
        } catch (\RuntimeException $e) {
            // Ya capturada (p. ej. el cliente recarga la página): se consulta su estado
            try {
                $res = $this->api('GET', '/v2/checkout/orders/' . rawurlencode($ref));
            } catch (\RuntimeException) {
                return PaymentResult::failed($ref, mb_substr($e->getMessage(), 0, 250));
            }
        }
        $capture = $res['purchase_units'][0]['payments']['captures'][0] ?? null;
        $status = (string) ($capture['status'] ?? $res['status'] ?? '');
        $data = ['status' => $res['status'] ?? null, 'capture_status' => $capture['status'] ?? null, 'payer' => $res['payer']['email_address'] ?? null];
        if ($status === 'COMPLETED') {
            return PaymentResult::paid($ref, (string) ($capture['id'] ?? ''), isset($capture['amount']['value']) ? (float) $capture['amount']['value'] : null, $data);
        }
        if ($status === 'PENDING') {
            return PaymentResult::pending($ref, $data);
        }
        return PaymentResult::failed($ref, 'PayPal: ' . ($status ?: 'sin estado'), $data);
    }

    // ---------------------------------------------------------------- API

    private function api(string $method, string $path, ?array $body = null, ?string $requestId = null): array
    {
        $headers = ['Authorization: Bearer ' . $this->accessToken(), 'Content-Type: application/json', 'Accept: application/json',
            'Prefer: return=representation'];
        if ($requestId !== null) {
            $headers[] = 'PayPal-Request-Id: ' . $requestId;
        }
        [$code, $res] = $this->http($method, $this->base() . $path, $headers, $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : ($method === 'POST' ? '{}' : null));
        if ($code < 200 || $code >= 300) {
            $detail = is_array($res) ? (($res['details'][0]['issue'] ?? null) ?? ($res['name'] ?? '') . ' ' . ($res['message'] ?? '')) : '';
            Log::channel('payments')->warning('PayPal {method} {path}: HTTP {code} {detail}', compact('method', 'path', 'code', 'detail'));
            throw new \RuntimeException("PayPal HTTP $code: " . trim((string) $detail));
        }
        return is_array($res) ? $res : [];
    }

    private function accessToken(): string
    {
        [$code, $res] = $this->http('POST', $this->base() . '/v1/oauth2/token', [
            'Authorization: Basic ' . base64_encode($this->opt('client_id', '') . ':' . $this->opt('client_secret', '')),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], 'grant_type=client_credentials');
        if ($code !== 200 || !is_array($res) || empty($res['access_token'])) {
            throw new \RuntimeException('PayPal: no se pudo obtener el token (HTTP ' . $code . '); revisa client_id/client_secret y sandbox');
        }
        return (string) $res['access_token'];
    }

    /** @return array{0:int,1:mixed} */
    private function http(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $raw === false ? curl_error($ch) : null;
        curl_close($ch);
        if ($raw === false) {
            throw new \RuntimeException('PayPal: sin conexión (' . $error . ')');
        }
        return [$code, json_decode((string) $raw, true)];
    }

    private function base(): string
    {
        return $this->opt('sandbox', true) ? self::API_TEST : self::API_LIVE;
    }
}
