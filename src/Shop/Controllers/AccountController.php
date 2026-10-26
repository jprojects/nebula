<?php
declare(strict_types=1);

namespace Nebula\Shop\Controllers;

use Nebula\Core\Log;
use Nebula\Shop\ShopException;

/**
 * Cuenta del cliente: acceso, alta, recuperar contraseña, pedidos, datos y direcciones.
 * Los formularios se envían por AJAX (store.js, data-ajax-form) y responden {ok, redirect | errors}.
 */
final class AccountController extends ShopController
{
    private const ADDRESS_FIELDS = ['label' => 60, 'name' => 150, 'address' => 255, 'city' => 100, 'postcode' => 20, 'province' => 100, 'country' => 2, 'phone' => 30];

    public function loginPage(): void
    {
        if ($this->shop->customer() !== null) {
            $this->shop->redirect($this->view->url('/cuenta'));
        }
        $this->shop->html('account/login', ['next' => $this->safeNext($this->query('next'))] + $this->meta($this->t('account.login')));
    }

    public function registerPage(): void
    {
        $this->shop->html('account/register', ['next' => $this->safeNext($this->query('next'))] + $this->meta($this->t('account.register')));
    }

    public function forgotPage(): void
    {
        $this->shop->html('account/forgot', $this->meta($this->t('account.forgot')));
    }

    public function resetPage(): void
    {
        $token = $this->query('token');
        $this->shop->html('account/reset', [
            'token' => $token,
            'valid' => $this->services->customers()->validResetToken($token) !== null,
        ] + $this->meta($this->t('account.reset')));
    }

    public function account(): void
    {
        $customer = $this->requireCustomer();
        $orders = $this->db->fetchAll(
            'SELECT id, number, token, status, payment_status, total, created_at FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 100',
            [$customer['id']]
        );
        $this->shop->html('account/orders', ['orders' => $orders] + $this->meta($this->t('account.title')));
    }

    public function profilePage(): void
    {
        $customer = $this->requireCustomer();
        $this->shop->html('account/profile', [
            'addresses' => $this->services->customers()->addresses($customer),
            'countries' => $this->db->fetchAll('SELECT code, name FROM countries WHERE active = 1 ORDER BY sort, name'),
        ] + $this->meta($this->t('account.profile')));
    }

    // ---------------------------------------------------------------- API

    public function login(): void
    {
        $this->services->customers()->login($this->input('email'), (string) ($this->shop->input()['password'] ?? ''));
        $this->shop->json(['ok' => true, 'redirect' => $this->safeNext($this->input('next')) ?? $this->view->url('/cuenta')]);
    }

    public function register(): void
    {
        $in = $this->shop->input();
        $errors = [];
        $email = mb_strtolower($this->input('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = $this->t('errors.email');
        }
        if ($this->input('name') === '') {
            $errors['name'] = $this->t('errors.required');
        }
        if (mb_strlen((string) ($in['password'] ?? '')) < 8) {
            $errors['password'] = $this->t('errors.password_short');
        }
        if (empty($in['accept_terms'])) {
            $errors['accept_terms'] = $this->t('errors.accept_terms');
        }
        if ($errors) {
            $this->fail($errors);
            return;
        }
        $customer = $this->services->customers()->register(
            ['email' => $email] + $in,
            (int) $this->services->pricing()->defaultGroup()['id'],
            $this->view->locale()
        );
        $this->services->mailer()->welcome($customer);
        $this->shop->json(['ok' => true, 'redirect' => $this->safeNext($this->input('next')) ?? $this->view->url('/cuenta')]);
    }

    public function logout(): void
    {
        $this->services->customers()->logout();
        $this->shop->json(['ok' => true, 'redirect' => $this->view->url('/')]);
    }

    /** Siempre responde lo mismo, exista o no la cuenta (no revela qué emails están registrados). */
    public function forgot(): void
    {
        $email = $this->input('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $reset = $this->services->customers()->createResetToken($email);
            if ($reset !== null) {
                $this->services->mailer()->passwordReset($reset['customer'], $this->view->urls->abs('/restablecer', ['token' => $reset['token']]));
                Log::channel('shop')->info('Enlace para restablecer la contraseña del cliente {id}', ['id' => $reset['customer']['id']]);
            }
        }
        $this->shop->json(['ok' => true, 'message' => $this->t('account.forgot_sent')]);
    }

    public function reset(): void
    {
        $password = (string) ($this->shop->input()['password'] ?? '');
        if (mb_strlen($password) < 8) {
            $this->fail(['password' => $this->t('errors.password_short')]);
            return;
        }
        if (!$this->services->customers()->resetPassword($this->input('token'), $password)) {
            throw new ShopException('reset_invalid');
        }
        $this->shop->json(['ok' => true, 'redirect' => $this->view->url('/cuenta')]);
    }

    public function profile(): void
    {
        $customer = $this->requireCustomer();
        $in = $this->shop->input();
        if ($this->input('name') === '') {
            $this->fail(['name' => $this->t('errors.required')]);
            return;
        }
        if (($in['password'] ?? '') !== '') {
            if (!password_verify((string) ($in['current_password'] ?? ''), (string) $customer['password_hash'])) {
                $this->fail(['current_password' => $this->t('errors.password_wrong')]);
                return;
            }
            if (mb_strlen((string) $in['password']) < 8) {
                $this->fail(['password' => $this->t('errors.password_short')]);
                return;
            }
        }
        $this->services->customers()->updateProfile((int) $customer['id'], $in);
        $this->shop->json(['ok' => true, 'message' => $this->t('account.saved')]);
    }

    /** Sustituye la libreta de direcciones: {addresses: [{label, name, address…}]} */
    public function addresses(): void
    {
        $customer = $this->requireCustomer();
        $list = [];
        foreach (array_slice((array) ($this->shop->input()['addresses'] ?? []), 0, 10) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $clean = [];
            foreach (self::ADDRESS_FIELDS as $k => $max) {
                $clean[$k] = mb_substr(trim((string) ($a[$k] ?? '')), 0, $max);
            }
            $clean['country'] = strtoupper($clean['country']);
            if ($clean['address'] !== '' && $clean['postcode'] !== '') {
                $list[] = $clean;
            }
        }
        $this->services->customers()->saveAddresses((int) $customer['id'], $list);
        $this->shop->json(['ok' => true, 'message' => $this->t('account.saved'), 'count' => count($list)]);
    }

    /** Solo rutas de esta tienda (nada de redirecciones a otros dominios). */
    private function safeNext(string $next): ?string
    {
        return $next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//') && !str_contains($next, '\\') ? $next : null;
    }
}
