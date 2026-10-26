<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\App;
use Nebula\Core\HttpException;
use Nebula\Core\Lang;
use Nebula\Core\Log;
use Nebula\Core\Session;
use Nebula\Remote\State;
use Nebula\Shop\Controllers\AccountController;
use Nebula\Shop\Controllers\CartController;
use Nebula\Shop\Controllers\CatalogController;
use Nebula\Shop\Controllers\CheckoutController;

/**
 * Tienda pública (public/index.php). Rutas por la ruta de la URL (/p/producto); las que empiezan por /api/
 * responden JSON para el JavaScript de la tienda (store.js).
 */
final class Shop
{
    /** [método(s), patrón, controlador, función] — los grupos con nombre pasan como argumentos */
    private const ROUTES = [
        ['GET', '#^/$#', CatalogController::class, 'home'],
        ['GET', '#^/c/(?<slug>[a-z0-9-]+)$#', CatalogController::class, 'category'],
        ['GET', '#^/p/(?<slug>[a-z0-9-]+)$#', CatalogController::class, 'product'],
        ['GET', '#^/buscar$#', CatalogController::class, 'search'],
        ['GET', '#^/api/search$#', CatalogController::class, 'suggest'],

        ['GET', '#^/carrito$#', CartController::class, 'page'],
        ['GET', '#^/api/cart$#', CartController::class, 'state'],
        ['POST', '#^/api/cart/add$#', CartController::class, 'add'],
        ['POST', '#^/api/cart/update$#', CartController::class, 'update'],
        ['POST', '#^/api/cart/coupon$#', CartController::class, 'coupon'],

        ['GET', '#^/checkout$#', CheckoutController::class, 'page'],
        ['POST', '#^/api/checkout/quote$#', CheckoutController::class, 'quote'],
        ['POST', '#^/api/checkout/place$#', CheckoutController::class, 'place'],
        ['GET', '#^/pedido/(?<number>[A-Za-z0-9_-]+)$#', CheckoutController::class, 'order'],
        ['GET', '#^/pedido/(?<number>[A-Za-z0-9_-]+)/pagar$#', CheckoutController::class, 'pay'],
        ['GET|POST', '#^/pay/(?<gateway>[a-z0-9_]+)/return$#', CheckoutController::class, 'payReturn'],
        ['GET|POST', '#^/pay/(?<gateway>[a-z0-9_]+)/cancel$#', CheckoutController::class, 'payCancel'],
        ['GET|POST', '#^/pay/(?<gateway>[a-z0-9_]+)/notify$#', CheckoutController::class, 'payNotify'],

        ['GET', '#^/entrar$#', AccountController::class, 'loginPage'],
        ['GET', '#^/registro$#', AccountController::class, 'registerPage'],
        ['GET', '#^/recuperar$#', AccountController::class, 'forgotPage'],
        ['GET', '#^/restablecer$#', AccountController::class, 'resetPage'],
        ['GET', '#^/cuenta$#', AccountController::class, 'account'],
        ['GET', '#^/cuenta/datos$#', AccountController::class, 'profilePage'],
        ['POST', '#^/api/account/login$#', AccountController::class, 'login'],
        ['POST', '#^/api/account/register$#', AccountController::class, 'register'],
        ['POST', '#^/api/account/logout$#', AccountController::class, 'logout'],
        ['POST', '#^/api/account/forgot$#', AccountController::class, 'forgot'],
        ['POST', '#^/api/account/reset$#', AccountController::class, 'reset'],
        ['POST', '#^/api/account/profile$#', AccountController::class, 'profile'],
        ['POST', '#^/api/account/addresses$#', AccountController::class, 'addresses'],
    ];

    /** Rutas POST que no llevan el token CSRF (las llama la pasarela, no el navegador del cliente) */
    private const NO_CSRF = ['payReturn', 'payCancel', 'payNotify'];

    /** Módulos de la gestión cuyos archivos son públicos en la tienda (/media/{id}) */
    private const PUBLIC_MEDIA = ['products', 'categories'];

    private const ASSET_TYPES = [
        'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2',
    ];

    public readonly App $app;
    public readonly Services $services;
    public readonly Lang $lang;
    public readonly Urls $urls;
    public readonly StoreView $view;
    private string $path = '/';

    public function __construct(public readonly array $config)
    {
        $this->app = new App($config); // BD, correo, archivos y log de la gestión
        $this->services = new Services($this->app->db);
        $this->urls = $this->services->urls();
        $this->lang = new Lang(NB_ROOT . '/lang', (string) ($config['shop']['locale'] ?? 'ca'), (string) ($config['fallback_locale'] ?? 'es'));
        $this->view = new StoreView($this->lang, $this->urls);
    }

    public function run(): void
    {
        $this->path = $this->requestPath();
        $this->securityHeaders();

        if (str_starts_with($this->path, '/assets/')) {
            $this->serveAsset(substr($this->path, 8));
            return;
        }
        if (preg_match('#^/media/(\d+)(/thumb)?$#', $this->path, $m)) {
            $this->serveMedia((int) $m[1], isset($m[2]));
            return;
        }
        if (State::maintenance() !== null && !str_starts_with($this->path, '/pay/')) {
            header('Retry-After: 120');
            $this->renderError(503);
            return;
        }

        Session::start(['session_name' => $this->config['shop']['session_name'] ?? 'NEBULASHOP']);
        $this->resolveLocale();

        try {
            if (preg_match('#^/idioma/([a-z]{2})$#', $this->path, $m)) {
                $this->switchLocale($m[1]);
                return;
            }
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            foreach (self::ROUTES as [$methods, $pattern, $class, $fn]) {
                if (!preg_match($pattern, $this->path, $m)) {
                    continue;
                }
                if (!in_array($method, explode('|', $methods), true) && !($method === 'HEAD' && str_contains($methods, 'GET'))) {
                    throw new HttpException(405);
                }
                if ($method === 'POST' && !in_array($fn, self::NO_CSRF, true)
                    && !Session::checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null)) {
                    throw new HttpException(419);
                }
                $args = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                Log::withContext(['shop' => $this->path]);
                $this->shareLayout();
                (new $class($this))->$fn(...$args);
                return;
            }
            throw new HttpException(404);
        } catch (ShopException $e) {
            $this->json(['ok' => false, 'error' => $this->view->t('errors.' . $e->getMessage(), $e->vars)], $e->status);
        } catch (HttpException $e) {
            $this->renderError($e->status);
        } catch (\Throwable $e) {
            Log::error('Excepción no controlada en la tienda: {message}', ['message' => $e->getMessage(), 'url' => $_SERVER['REQUEST_URI'] ?? null, 'exception' => $e]);
            $this->renderError(500, $e);
        }
    }

    // ---------------------------------------------------------------- contexto del visitante

    public function customer(): ?array
    {
        return $this->services->customers()->current();
    }

    /** Grupo (tarifa) del visitante: el de su cuenta o el de por defecto. */
    public function group(): array
    {
        $c = $this->customer();
        return $this->services->pricing()->group($c !== null && $c['group_id'] !== null ? (int) $c['group_id'] : null);
    }

    public function cart(): Cart
    {
        return new Cart();
    }

    /** Resumen del carrito valorado (sin envío) para la cabecera y el panel lateral. */
    public function cartSummary(): array
    {
        $cart = $this->cart();
        $customer = $this->customer();
        $quote = $this->services->calculator()->quote($cart->lines(), [
            'group' => $this->group(),
            'coupon' => $cart->coupon(),
            'email' => $customer['email'] ?? null,
            'customer_id' => $customer !== null ? (int) $customer['id'] : null,
        ]);
        if ($quote['lines'] != $cart->lines()) {
            $cart->replace($quote['lines']);
        }
        return $quote;
    }

    /** Lo que el JavaScript necesita del carrito (importes ya formateados). */
    public function cartPayload(?array $quote = null): array
    {
        $quote ??= $this->cartSummary();
        $v = $this->view;
        return [
            'count' => $quote['count'],
            'html' => $v->partial('partials/cart_drawer', ['quote' => $quote]),
            'subtotal' => $v->money($quote['subtotal']),
            'total' => $v->money($quote['total']),
            'notices' => array_map(fn ($n) => $v->t('cart.notice_' . $n['type'], ['name' => $n['name'] ?? '', 'qty' => $n['qty'] ?? '']), $quote['notices']),
        ];
    }

    // ---------------------------------------------------------------- respuestas

    public function json(mixed $data, int $status = 200): void
    {
        $this->app->json($data, $status);
    }

    public function html(string $template, array $vars = [], int $status = 200, ?string $layout = 'layout'): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $this->view->render($template, $vars, $layout);
    }

    public function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/') || isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /** Cuerpo de la petición: JSON (store.js) o formulario. */
    public function input(): array
    {
        static $input = null;
        if ($input === null) {
            $type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
            $input = str_contains($type, 'application/json')
                ? (array) (json_decode((string) file_get_contents('php://input'), true) ?: [])
                : $_POST;
        }
        return $input;
    }

    public function renderError(int $status, ?\Throwable $e = null): void
    {
        $key = in_array($status, [404, 403, 405, 419, 503], true) ? (string) $status : '500';
        if ($this->wantsJson()) {
            $this->json(['ok' => false, 'error' => $this->view->t('errors.http_' . $key)], $status);
            return;
        }
        try {
            $this->shareLayout();
            $this->html('error', [
                'title' => $this->view->t('errors.http_' . $key),
                'status' => $status,
                'debug' => !empty($this->config['debug']) && $e !== null ? (string) $e : null,
            ], $status);
        } catch (\Throwable $inner) {
            Log::critical('Fallo al mostrar el error de la tienda', ['exception' => $inner]);
            http_response_code($status);
            header('Content-Type: text/plain; charset=utf-8');
            echo $status;
        }
    }

    // ---------------------------------------------------------------- internos

    /** Ruta relativa a la tienda: /tienda/index.php/p/x → /p/x */
    private function requestPath(): string
    {
        $path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
        $prefix = $this->urls->prefix();
        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        } elseif (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php') {
            // Sin base_url: la carpeta del index.php (con el servidor de PHP, SCRIPT_NAME puede ser la URL pedida)
            $script = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'])), '/');
            if ($script !== '' && str_starts_with($path, $script)) {
                $path = substr($path, strlen($script));
            }
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, 10);
        }
        $path = '/' . trim($path, '/');
        return $path;
    }

    private function resolveLocale(): void
    {
        $locales = array_keys((array) ($this->config['shop']['locales'] ?? ['ca' => 'Català']));
        $locale = (string) Session::get('shop_locale', '');
        if ($locale === '') {
            $locale = (string) ($this->customer()['locale'] ?? '');
        }
        if ($locale === '' && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            foreach (explode(',', (string) $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $part) {
                $code = strtolower(substr(trim($part), 0, 2));
                if (in_array($code, $locales, true)) {
                    $locale = $code;
                    break;
                }
            }
        }
        if (in_array($locale, $locales, true)) {
            $this->lang->setLocale($locale);
        }
    }

    private function switchLocale(string $code): void
    {
        if (array_key_exists($code, (array) ($this->config['shop']['locales'] ?? []))) {
            Session::set('shop_locale', $code);
            $customer = $this->customer();
            if ($customer !== null) {
                $this->app->db->update('customers', ['locale' => $code], 'id', $customer['id']);
            }
        }
        $back = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $this->redirect($back !== '' && parse_url($back, PHP_URL_HOST) === parse_url('//' . $host, PHP_URL_HOST) ? $back : $this->urls->to('/'));
    }

    /** Variables de la cabecera y el pie, comunes a todas las páginas. */
    private function shareLayout(): void
    {
        $catalog = $this->services->catalog();
        $theme = (array) ($this->config['shop']['theme'] ?? []);
        $color = $theme['color'] ?? $this->config['branding']['color'] ?? null;
        $this->view->share([
            'customer' => $this->customer(),
            'cartCount' => $this->cart()->count(),
            'menu' => $catalog->menuTree(),
            'locales' => (array) ($this->config['shop']['locales'] ?? []),
            'brandColor' => is_string($color) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color) ? $color : null,
            'hasLogo' => $this->logoFile() !== null,
            'footerLinks' => (array) ($this->config['shop']['footer_links'] ?? []),
            'path' => $this->path,
        ]);
    }

    private function logoFile(): ?string
    {
        $logo = $this->config['shop']['theme']['logo'] ?? $this->config['branding']['logo'] ?? null;
        if (!is_string($logo) || $logo === '') {
            return null;
        }
        $file = realpath(str_starts_with($logo, '/') ? $logo : NB_ROOT . '/' . $logo);
        return $file && is_file($file) ? $file : null;
    }

    private function serveAsset(string $file): void
    {
        if ($file === 'logo') {
            $logo = $this->logoFile();
            $logo !== null ? $this->sendFile($logo) : http_response_code(404);
            return;
        }
        $base = realpath(NB_ROOT . '/templates/store/assets');
        $path = $base ? realpath($base . '/' . $file) : false;
        if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
            http_response_code(404);
            return;
        }
        $this->sendFile($path);
    }

    private function sendFile(string $path): void
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (!isset(self::ASSET_TYPES[$ext])) {
            http_response_code(404);
            return;
        }
        $etag = '"' . md5($path . filemtime($path) . filesize($path)) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=604800');
        if ($ext === 'svg') {
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:");
        }
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            return;
        }
        header('Content-Type: ' . self::ASSET_TYPES[$ext]);
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    /** Imágenes de productos y categorías (las del resto de módulos solo se ven desde la gestión). */
    private function serveMedia(int $id, bool $thumb): void
    {
        $row = $this->app->media()->get($id);
        if ($row === null || !in_array($row['module'], self::PUBLIC_MEDIA, true) || !str_starts_with((string) $row['mime'], 'image/')) {
            http_response_code(404);
            return;
        }
        header_remove('Cache-Control');
        $this->app->media()->send($row, $thumb, false);
    }

    private function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // form-action: Redsys recibe el formulario de pago por POST
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
            . "form-action 'self' https://sis.redsys.es https://sis-t.redsys.es:25443; frame-ancestors 'self'; base-uri 'self'");
    }
}
