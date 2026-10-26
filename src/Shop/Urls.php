<?php
declare(strict_types=1);

namespace Nebula\Shop;

/**
 * URLs de la tienda. Con 'shop.pretty_urls' son /p/producto; sin reescritura, /index.php/p/producto.
 * Las relativas a la raíz del servidor (empiezan por la carpeta de instalación) y las absolutas para correos y
 * pasarelas a partir de 'base_url'.
 */
final class Urls
{
    private readonly string $prefix;
    private readonly string $origin;

    /**
     * @param string $base URL absoluta de la tienda (ShopConfig::baseUrl()), sin barra final
     */
    public function __construct(string $base, private readonly bool $pretty)
    {
        $parts = parse_url($base) ?: [];
        $this->prefix = rtrim((string) ($parts['path'] ?? ''), '/');
        $this->origin = isset($parts['host'])
            ? ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')
            : '';
    }

    public static function fromConfig(): self
    {
        return new self(ShopConfig::baseUrl(), (bool) ShopConfig::get('shop.pretty_urls', true));
    }

    /** Carpeta de la tienda en el servidor ('' o '/tienda'). */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /** "/p/camiseta" → "/tienda/p/camiseta" (o "/tienda/index.php/p/camiseta"). */
    public function to(string $path = '/', array $query = []): string
    {
        $path = '/' . ltrim($path, '/');
        $url = $this->prefix . ($this->pretty || $path === '/' ? '' : '/index.php') . ($path === '/' ? '/' : $path);
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');
        return $url . ($query ? '?' . http_build_query($query) : '');
    }

    public function abs(string $path = '/', array $query = []): string
    {
        return $this->origin . $this->to($path, $query);
    }

    /** URL absoluta de la gestión (public/admin/). */
    public function admin(array $query = []): string
    {
        return $this->origin . $this->prefix . '/admin/index.php' . ($query ? '?' . http_build_query($query) : '');
    }

    /** Ficheros estáticos de templates/store/assets (sirve Shop, como la gestión con ?r=asset). */
    public function asset(string $file): string
    {
        $v = @filemtime(NB_ROOT . '/templates/store/assets/' . $file) ?: 0;
        return $this->to('/assets/' . $file, ['v' => $v ?: null]);
    }

    public function media(?int $id, bool $thumb = false): ?string
    {
        return $id ? $this->to('/media/' . $id . ($thumb ? '/thumb' : '')) : null;
    }

    public function product(string $slug): string
    {
        return $this->to('/p/' . rawurlencode($slug));
    }

    public function category(string $slug, array $query = []): string
    {
        return $this->to('/c/' . rawurlencode($slug), $query);
    }

    public function order(string $number, string $token, bool $absolute = false): string
    {
        $path = '/pedido/' . rawurlencode($number);
        return $absolute ? $this->abs($path, ['t' => $token]) : $this->to($path, ['t' => $token]);
    }
}
