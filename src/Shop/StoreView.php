<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Lang;
use Nebula\Core\Session;

/**
 * Plantillas de la tienda (templates/store/). Mismos helpers que la vista de la gestión ($this->e(), t(), icon()…)
 * más los de la tienda (url(), money(), img()…). Los textos están en lang/{idioma}/store.json.
 */
final class StoreView
{
    /** @var array<string,string>|null */
    private ?array $icons = null;
    /** Variables compartidas por todas las plantillas de la petición (cliente, carrito, menú…) */
    private array $shared = [];

    public function __construct(
        public readonly Lang $lang,
        public readonly Urls $urls,
        private readonly string $dir = NB_ROOT . '/templates/store',
    ) {
    }

    public function share(array $vars): void
    {
        $this->shared = $vars + $this->shared;
    }

    public function shared(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    public function render(string $template, array $vars = [], ?string $layout = 'layout'): string
    {
        $vars += $this->shared;
        $content = $this->renderFile($template, $vars);
        return $layout === null ? $content : $this->renderFile($layout, $vars + ['content' => $content]);
    }

    private function renderFile(string $__template, array $__vars): string
    {
        if (!preg_match('~^[A-Za-z0-9_/-]+$~', $__template) || !is_file($__file = $this->dir . '/' . $__template . '.php')) {
            throw new \RuntimeException("Plantilla de la tienda no encontrada: $__template");
        }
        extract($__vars, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Parcial sin layout. */
    public function partial(string $template, array $vars = []): string
    {
        return $this->render($template, $vars, null);
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function t(string $key, array $vars = []): string
    {
        return $this->lang->get($key, $vars, 'store');
    }

    public function json(mixed $data): string
    {
        return htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
    }

    public function url(string $path = '/', array $query = []): string
    {
        return $this->urls->to($path, $query);
    }

    public function asset(string $file): string
    {
        return $this->urls->asset($file);
    }

    public function img(?int $id, bool $thumb = false): ?string
    {
        return $this->urls->media($id, $thumb);
    }

    /**
     * HTML de un campo richtext (ya limpio: HtmlSanitizer) con las imágenes de la gestión
     * (index.php?r=media&id=N) cambiadas por las URLs públicas de la tienda.
     */
    public function richtext(?string $html): string
    {
        return (string) preg_replace_callback(
            '~(?:\.\./)*(?:admin/)?index\.php\?r=media&(?:amp;)?id=(\d+)((?:&(?:amp;)?thumb=1)?)[^"\']*~',
            fn ($m) => (string) $this->urls->media((int) $m[1], $m[2] !== ''),
            (string) $html
        );
    }

    public function money(float $amount): string
    {
        return Money::format($amount, $this->lang->locale());
    }

    public function date(?string $datetime, bool $time = false): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }
        $ts = strtotime($datetime);
        return $ts === false ? '' : date($time ? 'd/m/Y H:i' : 'd/m/Y', $ts);
    }

    public function csrfToken(): string
    {
        return Session::csrfToken();
    }

    public function locale(): string
    {
        return $this->lang->locale();
    }

    public function shopName(): string
    {
        return ShopConfig::shopName();
    }

    /** Icono Lucide en línea (el mismo juego que la gestión). */
    public function icon(string $name, string $class = '', ?string $label = null): string
    {
        if ($this->icons === null) {
            $this->icons = (array) require NB_ROOT . '/templates/default/icons/lucide.php';
        }
        $inner = $this->icons[$name] ?? null;
        if ($inner === null) {
            return '';
        }
        $a11y = $label !== null ? ' role="img" aria-label="' . $this->e($label) . '"' : ' aria-hidden="true" focusable="false"';
        return '<svg class="nb-icon ' . $this->e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $a11y . '>' . $inner . '</svg>';
    }

    /** Interior SVG de varios iconos para que store.js los use (<template> del layout). */
    public function iconMap(array $names): array
    {
        $this->icon('x');
        return array_intersect_key((array) $this->icons, array_flip($names));
    }
}
