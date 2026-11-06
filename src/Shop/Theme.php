<?php
declare(strict_types=1);

namespace Nebula\Shop;

/**
 * Tema de la tienda: una carpeta fuera del núcleo (config shop.theme.dir, p. ej. 'themes/mitienda') con las
 * plantillas y los assets que sustituyen a los de templates/store/. Lo que el tema no tiene se toma del núcleo, de
 * modo que una tienda puede cambiar solo partials/header.php y añadir assets/theme.css, y seguir actualizándose.
 */
final class Theme
{
    public const CORE = NB_ROOT . '/templates/store';

    /** @var list<string>|null */
    private static ?array $dirs = null;

    /** Carpetas en las que se buscan plantillas y assets: la del tema (si existe) y la del núcleo. */
    public static function dirs(): array
    {
        if (self::$dirs === null) {
            self::$dirs = [self::CORE];
            $dir = ShopConfig::get('shop.theme.dir');
            if (is_string($dir) && $dir !== '') {
                $path = realpath(str_starts_with($dir, '/') ? $dir : NB_ROOT . '/' . $dir);
                if ($path && is_dir($path) && $path !== realpath(self::CORE)) {
                    array_unshift(self::$dirs, $path);
                }
            }
        }
        return self::$dirs;
    }

    /** Fichero de una plantilla ('product', 'partials/header'…) o null si no existe ni en el tema ni en el núcleo. */
    public static function template(string $name): ?string
    {
        if (!preg_match('~^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$~', $name)) {
            return null;
        }
        foreach (self::dirs() as $dir) {
            if (is_file($file = $dir . '/' . $name . '.php')) {
                return $file;
            }
        }
        return null;
    }

    /** Fichero de un asset ('store.css', 'fonts/lato.woff2'…) dentro de {tema|núcleo}/assets/, o null. */
    public static function asset(string $file): ?string
    {
        if ($file === '' || str_contains($file, "\0")) {
            return null;
        }
        foreach (self::dirs() as $dir) {
            $base = realpath($dir . '/assets');
            $path = $base ? realpath($base . '/' . $file) : false;
            if ($base && $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR) && is_file($path)) {
                return $path;
            }
        }
        return null;
    }
}
