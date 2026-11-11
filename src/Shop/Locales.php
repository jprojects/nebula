<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;

/**
 * Idiomas de la tienda (tabla shop_languages, Configuración → Idiomas). El de por defecto es el de los textos del
 * catálogo (products.name, categories.name…); los demás se traducen en cada producto y categoría.
 *
 * Si la tabla todavía no existe (instalación sin migrar) o está vacía, se usan 'shop.locales' y 'shop.locale'.
 */
final class Locales
{
    /** @var array<string,string>|null código => nombre, activos y en orden */
    private ?array $active = null;
    private ?string $default = null;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string,string> código => nombre de los idiomas activos (el de por defecto siempre está) */
    public function all(): array
    {
        $this->load();
        return $this->active;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function default(): string
    {
        $this->load();
        return $this->default;
    }

    public function has(string $code): bool
    {
        return isset($this->all()[$code]);
    }

    /** ¿Hay que traducir? (el idioma no es el de los datos del catálogo) */
    public function isDefault(string $code): bool
    {
        return $code === $this->default();
    }

    private function load(): void
    {
        if ($this->active !== null) {
            return;
        }
        $rows = [];
        try {
            $rows = $this->db->fetchAll('SELECT code, name, is_default FROM shop_languages WHERE active = 1 OR is_default = 1 ORDER BY sort, name');
        } catch (\PDOException) {
            // tabla sin crear: se usa la configuración
        }
        if ($rows) {
            $this->active = [];
            foreach ($rows as $r) {
                $this->active[(string) $r['code']] = (string) $r['name'];
                if ((int) $r['is_default'] === 1 && $this->default === null) {
                    $this->default = (string) $r['code'];
                }
            }
            $this->default ??= (string) array_key_first($this->active);
            return;
        }
        $this->active = array_map('strval', (array) ShopConfig::get('shop.locales', ['ca' => 'Català']));
        $default = (string) ShopConfig::get('shop.locale', 'ca');
        $this->default = isset($this->active[$default]) ? $default : (string) (array_key_first($this->active) ?? 'ca');
        $this->active += [$this->default => strtoupper($this->default)];
    }
}
