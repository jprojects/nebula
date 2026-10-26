<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

use Nebula\Shop\Money;

/**
 * Redsys (TPV virtual) por redirección, firma HMAC_SHA256_V1.
 *
 * config 'payments.redsys': sandbox, merchant_code (FUC), terminal, secret (clave SHA-256 en base64), currency (978),
 * merchant_name, pay_methods. El TPV de pruebas público de Redsys es FUC 999008881, terminal 1 y clave
 * sq7HjrUOBfKmC576ILgskD5srU870gJ7 (tarjeta 4548810000000003, caducidad futura, CVV 123).
 *
 * El resultado llega por la notificación online (Ds_MerchantURL, /pay/redsys/notify). Si el comercio no puede
 * recibirla (p. ej. en local), la vuelta a URLOK trae los mismos parámetros firmados y también se aceptan.
 */
final class RedsysGateway extends Gateway
{
    private const URL_LIVE = 'https://sis.redsys.es/sis/realizarPago';
    private const URL_TEST = 'https://sis-t.redsys.es:25443/sis/realizarPago';
    private const LANGUAGES = ['es' => '001', 'en' => '002', 'ca' => '003', 'fr' => '004', 'de' => '005', 'it' => '007', 'pt' => '009'];

    public function available(): bool
    {
        return $this->opt('merchant_code') !== null && $this->opt('secret') !== null && function_exists('openssl_encrypt');
    }

    /** 12 dígitos: el id del pago (6) y la hora (6), para no repetir número en el TPV aunque se reinstale la tienda. */
    public function reference(int $paymentId): string
    {
        return str_pad((string) ($paymentId % 1000000), 6, '0', STR_PAD_LEFT) . substr((string) time(), -6);
    }

    public function start(array $order, array $payment, PaymentUrls $urls): PaymentAction
    {
        $params = [
            'DS_MERCHANT_AMOUNT' => (string) Money::cents((float) $payment['amount']),
            'DS_MERCHANT_ORDER' => (string) $payment['reference'],
            'DS_MERCHANT_MERCHANTCODE' => (string) $this->opt('merchant_code'),
            'DS_MERCHANT_CURRENCY' => (string) $this->opt('currency', '978'),
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_TERMINAL' => (string) $this->opt('terminal', '1'),
            'DS_MERCHANT_MERCHANTURL' => $urls->notify,
            'DS_MERCHANT_URLOK' => $urls->return,
            'DS_MERCHANT_URLKO' => $urls->cancel,
            'DS_MERCHANT_MERCHANTNAME' => mb_substr((string) $this->opt('merchant_name', $urls->shopName), 0, 25),
            'DS_MERCHANT_CONSUMERLANGUAGE' => self::LANGUAGES[$urls->locale] ?? '001',
            'DS_MERCHANT_PRODUCTDESCRIPTION' => mb_substr('Pedido ' . $order['number'], 0, 125),
            'DS_MERCHANT_MERCHANTDATA' => (string) $payment['id'],
        ];
        if ($this->opt('pay_methods') !== null) {
            $params['DS_MERCHANT_PAYMETHODS'] = (string) $this->opt('pay_methods');
        }
        $encoded = base64_encode(json_encode($params, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return PaymentAction::form($this->opt('sandbox', true) ? self::URL_TEST : self::URL_LIVE, [
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $this->sign($encoded, $params['DS_MERCHANT_ORDER']),
        ]);
    }

    public function notify(array $get, array $post, string $body): ?PaymentResult
    {
        return $this->verify($post);
    }

    /** URLOK / URLKO: Redsys añade los parámetros firmados; si no vienen, se espera a la notificación. */
    public function complete(array $payment, array $get, array $post): PaymentResult
    {
        $result = $this->verify($get + $post);
        if ($result !== null && $result->reference === (string) $payment['reference']) {
            return $result;
        }
        return PaymentResult::pending((string) $payment['reference']);
    }

    /** Comprueba la firma y traduce Ds_Response. */
    private function verify(array $input): ?PaymentResult
    {
        $encoded = is_string($input['Ds_MerchantParameters'] ?? null) ? $input['Ds_MerchantParameters'] : '';
        $signature = is_string($input['Ds_Signature'] ?? null) ? $input['Ds_Signature'] : '';
        if ($encoded === '' || $signature === '') {
            return null;
        }
        $params = json_decode((string) base64_decode(strtr($encoded, '-_', '+/')), true);
        if (!is_array($params)) {
            return null;
        }
        $params = array_change_key_case($params, CASE_UPPER);
        $order = (string) ($params['DS_ORDER'] ?? '');
        $expected = $this->sign($encoded, $order);
        if ($order === '' || !hash_equals(strtr($expected, '+/', '-_'), strtr($signature, '+/', '-_'))) {
            return null;
        }
        $response = (int) ($params['DS_RESPONSE'] ?? 9999);
        $data = array_intersect_key($params, array_flip([
            'DS_DATE', 'DS_HOUR', 'DS_AMOUNT', 'DS_CURRENCY', 'DS_ORDER', 'DS_RESPONSE', 'DS_AUTHORISATIONCODE',
            'DS_CARD_COUNTRY', 'DS_CARD_BRAND', 'DS_PROCESSEDPAYMETHOD', 'DS_SECUREPAYMENT', 'DS_ERRORCODE',
        ]));
        if ($response >= 0 && $response <= 99) {
            $amount = isset($params['DS_AMOUNT']) ? ((int) $params['DS_AMOUNT']) / 100 : null;
            return PaymentResult::paid($order, (string) ($params['DS_AUTHORISATIONCODE'] ?? ''), $amount, $data);
        }
        return PaymentResult::failed($order, 'Ds_Response ' . str_pad((string) $response, 4, '0', STR_PAD_LEFT), $data);
    }

    /** Firma HMAC_SHA256_V1: clave diversificada con 3DES sobre el número de pedido. */
    private function sign(string $encodedParams, string $order): string
    {
        $key = (string) base64_decode((string) $this->opt('secret', ''));
        $length = (int) ceil(strlen($order) / 8) * 8;
        $diversified = substr((string) openssl_encrypt(
            str_pad($order, $length, "\0"),
            'des-ede3-cbc',
            $key,
            OPENSSL_RAW_DATA,
            "\0\0\0\0\0\0\0\0"
        ), 0, $length);
        return base64_encode(hash_hmac('sha256', $encodedParams, $diversified, true));
    }
}
