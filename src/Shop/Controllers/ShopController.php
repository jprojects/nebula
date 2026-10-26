<?php
declare(strict_types=1);

namespace Nebula\Shop\Controllers;

use Nebula\Core\Database;
use Nebula\Shop\Services;
use Nebula\Shop\Shop;
use Nebula\Shop\ShopException;
use Nebula\Shop\StoreView;

abstract class ShopController
{
    protected readonly Database $db;
    protected readonly Services $services;
    protected readonly StoreView $view;

    public function __construct(protected readonly Shop $shop)
    {
        $this->db = $shop->app->db;
        $this->services = $shop->services;
        $this->view = $shop->view;
    }

    protected function t(string $key, array $vars = []): string
    {
        return $this->view->t($key, $vars);
    }

    protected function query(string $name, string $default = ''): string
    {
        $v = $_GET[$name] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    protected function input(string $name, string $default = ''): string
    {
        $v = $this->shop->input()[$name] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    /** Errores de validación por campo → 422 con {ok:false, errors:{campo: mensaje}} */
    protected function fail(array $errors, ?string $message = null): void
    {
        $this->shop->json(['ok' => false, 'error' => $message ?? $this->t('errors.check_form'), 'errors' => $errors], 422);
    }

    protected function requireCustomer(): array
    {
        $c = $this->shop->customer();
        if ($c === null) {
            if ($this->shop->wantsJson()) {
                throw new ShopException('login_required', [], 401);
            }
            $this->shop->redirect($this->view->url('/entrar', ['next' => (string) ($_SERVER['REQUEST_URI'] ?? '')]));
        }
        return $c;
    }

    /** Cabecera <head>: título, descripción y URL canónica. */
    protected function meta(string $title, ?string $description = null, ?string $canonical = null): array
    {
        return ['title' => $title, 'metaDescription' => $description, 'canonical' => $canonical];
    }
}
