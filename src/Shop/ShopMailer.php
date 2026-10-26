<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;
use Nebula\Core\Lang;
use Nebula\Core\Log;
use Nebula\Mail\Mail;

/**
 * Correos de la tienda (templates/store/emails/), en el idioma del pedido o del cliente.
 * Un fallo de envío nunca interrumpe el pedido: queda en el log de correo.
 */
final class ShopMailer
{
    public function __construct(private readonly Database $db, private readonly Urls $urls)
    {
    }

    /** Pedido recibido (con las instrucciones si el pago es fuera de línea) + copia a la tienda. */
    public function orderPlaced(array $order): void
    {
        $this->sendOrder($order, 'order_placed', 'mail.order_placed_subject');
        $this->notifyShop($order, 'mail.shop_new_order');
    }

    public function orderPaid(array $order): void
    {
        $this->sendOrder($order, 'order_paid', 'mail.order_paid_subject');
        $this->notifyShop($order, 'mail.shop_paid_order');
    }

    public function statusChanged(array $order, string $status, ?string $note): bool
    {
        $trackingUrl = null;
        if ($order['tracking_number'] && $order['shipping_method_id']) {
            $tpl = (string) $this->db->fetchValue('SELECT tracking_url FROM shipping_methods WHERE id = ?', [$order['shipping_method_id']]);
            $trackingUrl = str_contains($tpl, '{tracking}') ? str_replace('{tracking}', rawurlencode((string) $order['tracking_number']), $tpl) : null;
        }
        return $this->sendOrder($order, 'order_status', 'mail.order_status_subject', ['status' => $status, 'note' => $note, 'trackingUrl' => $trackingUrl]);
    }

    public function passwordReset(array $customer, string $link): bool
    {
        $view = $this->view($customer['locale'] ?? null);
        return $this->send(
            (string) $customer['email'],
            (string) $customer['name'],
            $view->t('mail.reset_subject', ['shop' => ShopConfig::shopName()]),
            $view->render('emails/password_reset', ['customer' => $customer, 'link' => $link], 'emails/layout')
        );
    }

    public function welcome(array $customer): bool
    {
        $view = $this->view($customer['locale'] ?? null);
        return $this->send(
            (string) $customer['email'],
            (string) $customer['name'],
            $view->t('mail.welcome_subject', ['shop' => ShopConfig::shopName()]),
            $view->render('emails/welcome', ['customer' => $customer, 'link' => $this->urls->abs('/cuenta')], 'emails/layout')
        );
    }

    private function sendOrder(array $order, string $template, string $subjectKey, array $vars = []): bool
    {
        $view = $this->view($order['locale'] ?? null);
        return $this->send(
            (string) $order['email'],
            (string) $order['bill_name'],
            $view->t($subjectKey, ['number' => $order['number'], 'shop' => ShopConfig::shopName()]),
            $view->render('emails/' . $template, $this->orderVars($order) + $vars, 'emails/layout')
        );
    }

    private function notifyShop(array $order, string $subjectKey): void
    {
        $to = ShopConfig::notifyEmail();
        if ($to === null) {
            return;
        }
        $view = $this->view((string) ShopConfig::get('shop.locale', 'ca'));
        $this->send($to, ShopConfig::shopName(), $view->t($subjectKey, ['number' => $order['number'], 'total' => $view->money((float) $order['total'])]),
            $view->render('emails/order_shop', $this->orderVars($order) + [
                'adminUrl' => $this->urls->admin(['r' => 'edit', 'm' => 'orders', 'id' => $order['id']]),
            ], 'emails/layout'));
    }

    private function orderVars(array $order): array
    {
        $method = $order['payment_method_id'] ? $this->db->fetch('SELECT * FROM payment_methods WHERE id = ?', [$order['payment_method_id']]) : null;
        return [
            'order' => $order,
            'items' => $this->db->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$order['id']]),
            'instructions' => $method ? OrderService::instructions($method, $order) : null,
            'orderUrl' => $this->urls->order((string) $order['number'], (string) $order['token'], true),
        ];
    }

    private function view(?string $locale): StoreView
    {
        $lang = new Lang(NB_ROOT . '/lang', (string) ShopConfig::get('shop.locale', 'ca'), (string) ShopConfig::get('fallback_locale', 'es'));
        if ($locale !== null && $locale !== '') {
            $lang->setLocale($locale);
        }
        $view = new StoreView($lang, $this->urls);
        $view->share(['absolute' => true]);
        return $view;
    }

    private function send(string $to, string $name, string $subject, string $html): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            return Mail::trySend(Mail::message()->to($to, $name)->subject($subject)->html($html));
        } catch (\Throwable $e) {
            Log::channel('mail')->error('Correo de la tienda no enviado: {message}', ['message' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }
}
