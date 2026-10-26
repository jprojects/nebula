<?php
declare(strict_types=1);

namespace Nebula\Shop;

final class Text
{
    /** "Camiseta Niño 100% algodón" → "camiseta-nino-100-algodon" */
    public static function slug(string $text, int $max = 150): string
    {
        $text = mb_strtolower(trim($text));
        if (function_exists('transliterator_transliterate')) {
            $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        } else {
            $text = strtr($text, ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'í' => 'i', 'ï' => 'i', 'ò' => 'o',
                'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c', 'l·l' => 'll']);
        }
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        return substr(trim($text, '-'), 0, $max) ?: 'item';
    }

    /** Slug único en una tabla (añade -2, -3… si ya existe en otro registro). */
    public static function uniqueSlug(\Nebula\Core\Database $db, string $table, string $base, ?string $exceptId): string
    {
        $slug = $base;
        for ($i = 2; $i < 1000; $i++) {
            $taken = $db->fetchValue(
                'SELECT id FROM ' . \Nebula\Core\Database::ident($table) . ' WHERE slug = ?' . ($exceptId !== null ? ' AND id <> ?' : ''),
                $exceptId !== null ? [$slug, $exceptId] : [$slug]
            );
            if ($taken === null) {
                return $slug;
            }
            $slug = $base . '-' . $i;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }

    /** Lista de ids enteros a partir de JSON, lista o texto "1,2". */
    public static function ids(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }
        $out = [];
        foreach (is_array($value) ? $value : [] as $v) {
            if (is_numeric($v) && (int) $v > 0) {
                $out[] = (int) $v;
            }
        }
        return array_values(array_unique($out));
    }

    /** JSON de una columna a array (null/ inválido → []). */
    public static function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
