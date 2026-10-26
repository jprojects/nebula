<?php
declare(strict_types=1);

namespace Nebula\Shop;

/**
 * Configuración de la instalación al alcance de los servicios de la tienda y de los hooks de la gestión
 * (que solo reciben la BD). La fija src/bootstrap.php.
 */
final class ShopConfig
{
    private static array $config = [];

    public static function set(array $config): void
    {
        self::$config = $config;
    }

    public static function all(): array
    {
        return self::$config;
    }

    /** Valor por ruta con puntos: ShopConfig::get('shop.currency', 'EUR'). */
    public static function get(string $path, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value ?? $default;
    }

    public static function shopName(): string
    {
        $name = self::get('shop.name') ?? self::get('branding.company') ?? self::get('app_name', 'Nebula');
        return trim((string) $name) !== '' ? trim((string) $name) : 'Nebula';
    }

    public static function currency(): string
    {
        $c = strtoupper((string) self::get('shop.currency', 'EUR'));
        return preg_match('/^[A-Z]{3}$/', $c) ? $c : 'EUR';
    }

    /** Correo de la tienda que recibe la copia de los pedidos. */
    public static function notifyEmail(): ?string
    {
        $email = (string) (self::get('shop.notify_email') ?? self::get('mail.from.address', ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** URL pública de la tienda sin barra final (config 'base_url' o la de la petición). */
    public static function baseUrl(): string
    {
        $base = (string) (self::get('base_url') ?? '');
        if ($base === '' && isset($_SERVER['HTTP_HOST'])) {
            $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $dir = rtrim(dirname($script), '/');
            if (str_ends_with($dir, '/admin')) {
                $dir = substr($dir, 0, -6); // desde la gestión: la tienda está un nivel por encima
            }
            $base = ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $dir;
        }
        return rtrim($base, '/');
    }
}
