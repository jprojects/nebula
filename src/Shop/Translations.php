<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;

/**
 * Traducciones de productos y categorías. En la gestión son un repeater (products.translations,
 * categories.translations: una fila por idioma); al guardar se copian a product_translations y
 * category_translations, que es lo que consulta la tienda. Lo que una traducción deja vacío se muestra en el
 * idioma por defecto.
 *
 * Los slugs son únicos entre todos los idiomas y registros (un mismo registro puede repetir el suyo en varios
 * idiomas): así cualquier URL, en el idioma que sea, lleva a un solo producto o categoría.
 */
final class Translations
{
    /** tabla => [tabla de traducciones, columna del id, campos traducibles] */
    private const TABLES = [
        'products' => ['product_translations', 'product_id', ['name', 'slug', 'summary', 'description', 'meta_title', 'meta_description']],
        'categories' => ['category_translations', 'category_id', ['name', 'slug', 'description', 'meta_title', 'meta_description']],
    ];

    /** @return list<string> campos traducibles de la tabla */
    public static function fields(string $table): array
    {
        return self::def($table)[2];
    }

    /**
     * Filas de products o categories con los textos del idioma (si no es el de por defecto).
     * Se conserva el slug original en 'base_slug'.
     */
    public static function apply(Database $db, string $table, array $rows, string $locale, Locales $locales): array
    {
        if (!$rows || $locales->isDefault($locale)) {
            return $rows;
        }
        [$tt, $fk, $fields] = self::def($table);
        $ids = array_values(array_unique(array_map(fn ($r) => (int) $r['id'], $rows)));
        $found = [];
        foreach ($db->fetchAll(
            "SELECT * FROM $tt WHERE locale = ? AND $fk IN (" . implode(',', array_fill(0, count($ids), '?')) . ')',
            [$locale, ...$ids]
        ) as $t) {
            $found[(int) $t[$fk]] = $t;
        }
        foreach ($rows as &$r) {
            $r['base_slug'] = $r['slug'];
            foreach ($fields as $f) {
                $value = $found[(int) $r['id']][$f] ?? null;
                if ($value !== null && trim((string) $value) !== '') {
                    $r[$f] = $value;
                }
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Repeater de traducciones listo para guardar (beforeSave de los hooks): una fila por idioma, sin el de por
     * defecto, con su slug (del nombre si está vacío) único.
     * @return array|string filas limpias, o una clave de idioma de error
     */
    public static function normalize(Database $db, string $table, mixed $value, ?string $id, Locales $locales): array|string
    {
        $fields = self::fields($table);
        $out = [];
        $seen = [];
        foreach (Text::json($value) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $locale = trim((string) ($row['locale'] ?? ''));
            if ($locale === '' || trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }
            if ($locales->isDefault($locale)) {
                return 'validation.translation_default';
            }
            if (isset($seen[$locale])) {
                return 'validation.translation_duplicated';
            }
            $seen[$locale] = true;
            $clean = ['locale' => $locale];
            foreach ($fields as $f) {
                $v = $row[$f] ?? null;
                $clean[$f] = is_string($v) && trim($v) !== '' ? trim($v) : null;
            }
            $base = $clean['slug'] !== null ? Text::slug($clean['slug']) : Text::slug((string) $clean['name']);
            $clean['slug'] = self::uniqueSlug($db, $table, $base, $id);
            $out[] = $clean;
        }
        return $out;
    }

    /** Copia el repeater a la tabla de traducciones (afterSave). */
    public static function sync(Database $db, string $table, int $id, array $rows): void
    {
        [$tt, $fk, $fields] = self::def($table);
        $db->query("DELETE FROM $tt WHERE $fk = ?", [$id]);
        foreach ($rows as $row) {
            if (!is_array($row) || trim((string) ($row['locale'] ?? '')) === '' || trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }
            $values = [$fk => $id, 'locale' => (string) $row['locale']];
            foreach ($fields as $f) {
                $values[$f] = isset($row[$f]) && trim((string) $row[$f]) !== '' ? (string) $row[$f] : null;
            }
            $values['slug'] ??= Text::slug((string) $row['name']);
            $db->insert($tt, $values);
        }
    }

    /**
     * Slug que no usa ningún otro registro de la tabla, en ningún idioma (el propio registro sí puede repetirlo).
     */
    public static function uniqueSlug(Database $db, string $table, string $base, ?string $exceptId): string
    {
        $slug = $base;
        for ($i = 2; $i < 1000; $i++) {
            if (!self::slugTaken($db, $table, $slug, $exceptId)) {
                return $slug;
            }
            $slug = mb_substr($base, 0, 200) . '-' . $i;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }

    public static function slugTaken(Database $db, string $table, string $slug, ?string $exceptId): bool
    {
        [$tt, $fk] = self::def($table);
        $except = $exceptId !== null ? (int) $exceptId : 0;
        return $db->fetchValue(
            "SELECT 1 FROM $table WHERE slug = ? AND id <> ? UNION ALL SELECT 1 FROM $tt WHERE slug = ? AND $fk <> ? LIMIT 1",
            [$slug, $except, $slug, $except]
        ) !== null;
    }

    /**
     * Id del registro activo con ese slug en cualquier idioma (el de la tabla o una traducción).
     */
    public static function findBySlug(Database $db, string $table, string $slug): ?int
    {
        [$tt, $fk] = self::def($table);
        $id = $db->fetchValue(
            "SELECT id FROM $table WHERE slug = ? AND active = 1
             UNION ALL SELECT t.$fk FROM $tt t JOIN $table r ON r.id = t.$fk WHERE t.slug = ? AND r.active = 1 LIMIT 1",
            [$slug, $slug]
        );
        return $id !== null ? (int) $id : null;
    }

    /** @return array{0:string,1:string,2:list<string>} */
    private static function def(string $table): array
    {
        return self::TABLES[$table] ?? throw new \InvalidArgumentException("Tabla sin traducciones: $table");
    }
}
