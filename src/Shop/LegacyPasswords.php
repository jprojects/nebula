<?php
declare(strict_types=1);

namespace Nebula\Shop;

/**
 * Contraseñas de clientes importados de otra tienda que no están en un formato de password_hash().
 *
 * Al entrar, Customers::login prueba primero password_verify (bcrypt y argon2: Joomla 3.2+ ya los usa, igual que
 * WordPress 6.8+) y, si el hash no es de PHP, los formatos de 'shop.legacy_passwords'. Si la contraseña es
 * correcta se vuelve a guardar con password_hash(): cada cliente pasa al formato de Nebula la primera vez que
 * entra, sin tener que restablecerla.
 *
 * Formatos (los mismos que valida Joomla, de la 1.5 a la 6):
 *   joomla   phpass portable ($P$…), {SHA256}hash:sal (3.2.0), md5(contraseña + sal):sal y md5 sin sal
 */
final class LegacyPasswords
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /** ¿Lo entiende password_verify? (bcrypt, argon2…) */
    public static function isNative(string $hash): bool
    {
        return !empty(password_get_info($hash)['algo']);
    }

    /** Formatos activos: config 'shop.legacy_passwords'. */
    public static function formats(): array
    {
        return array_values(array_filter((array) ShopConfig::get('shop.legacy_passwords', ['joomla']), 'is_string'));
    }

    /**
     * Comprueba una contraseña con su hash, nativo o de uno de los formatos.
     * @param list<string>|null $formats null = los de la configuración
     */
    public static function verify(string $password, string $hash, ?array $formats = null): bool
    {
        if ($hash === '') {
            return false;
        }
        if (self::isNative($hash)) {
            return password_verify($password, $hash);
        }
        foreach ($formats ?? self::formats() as $format) {
            $ok = match ($format) {
                'joomla' => self::joomla($password, $hash),
                default => false,
            };
            if ($ok) {
                return true;
            }
        }
        return false;
    }

    /** Joomla: UserHelper::verifyPassword (y los MD5Handler/PHPassHandler de la 4 en adelante). */
    private static function joomla(string $password, string $hash): bool
    {
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return self::phpass($password, $hash);
        }
        if (str_starts_with($hash, '{SHA256}')) {
            $salt = explode(':', $hash, 2)[1] ?? '';
            $test = '{SHA256}' . ($salt !== '' ? hash('sha256', $password . $salt) . ':' . $salt : hash('sha256', $password));
            return hash_equals($hash, $test);
        }
        if (preg_match('/^[0-9a-f]{32}(:.*)?$/', $hash)) {
            $salt = explode(':', $hash, 2)[1] ?? '';
            // Sin sal pero con «:» al final, Joomla también lo compara con los dos puntos
            $test = md5($password . $salt) . ($salt !== '' ? ':' . $salt : (str_contains($hash, ':') ? ':' : ''));
            return hash_equals($hash, $test);
        }
        return false;
    }

    /** phpass portable (md5 iterado), el de Joomla 2.5/3.x y WordPress hasta la 6.7. */
    private static function phpass(string $password, string $hash): bool
    {
        if (strlen($hash) !== 34) {
            return false;
        }
        $log2 = strpos(self::ITOA64, $hash[3]);
        if ($log2 === false || $log2 < 7 || $log2 > 30) {
            return false;
        }
        $count = 1 << $log2;
        $salt = substr($hash, 4, 8);
        $h = md5($salt . $password, true);
        do {
            $h = md5($h . $password, true);
        } while (--$count);
        return hash_equals($hash, substr($hash, 0, 12) . self::encode64($h, 16));
    }

    private static function encode64(string $input, int $count): string
    {
        $out = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $out .= self::ITOA64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $out .= self::ITOA64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $out .= self::ITOA64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $out .= self::ITOA64[($value >> 18) & 0x3f];
        } while ($i < $count);
        return $out;
    }
}
