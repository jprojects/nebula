<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\ModuleHooks;
use Nebula\Core\User;
use Nebula\Shop\Text;

/**
 * Categorías: slug automático, sin ciclos en el árbol y, al borrar, fuera de los productos que la tenían
 * (products.categories es una lista JSON; product_categories se borra sola por la clave foránea).
 */
final class CategoriesHooks extends ModuleHooks
{
    public function beforeSave(array &$data, ?string $id, ?array $existing, User $user): ?string
    {
        if (array_key_exists('slug', $data) || $id === null) {
            $base = trim((string) ($data['slug'] ?? '')) !== '' ? Text::slug((string) $data['slug']) : Text::slug((string) ($data['name'] ?? $existing['name'] ?? ''));
            $data['slug'] = Text::uniqueSlug($this->db, 'categories', $base, $id);
        }
        $parent = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
        if ($id !== null && $parent !== null) {
            // El padre no puede ser la propia categoría ni una de sus descendientes
            for ($i = 0, $p = $parent; $p !== null && $i < 50; $i++) {
                if ($p === (int) $id) {
                    return 'validation.parent_cycle';
                }
                $next = $this->db->fetchValue('SELECT parent_id FROM categories WHERE id = ?', [$p]);
                $p = $next !== null ? (int) $next : null;
            }
        }
        return null;
    }

    public function afterDelete(string $id, User $user): void
    {
        $this->db->query('UPDATE categories SET parent_id = NULL WHERE parent_id = ?', [$id]);
        $rows = $this->db->fetchAll(
            'SELECT id, categories FROM products WHERE CASE WHEN JSON_VALID(categories) THEN JSON_CONTAINS(categories, JSON_QUOTE(?)) OR JSON_CONTAINS(categories, ?) ELSE 0 END',
            [$id, $id]
        );
        foreach ($rows as $row) {
            $ids = array_values(array_filter(Text::ids($row['categories']), fn ($c) => $c !== (int) $id));
            $this->db->update('products', ['categories' => json_encode(array_map('strval', $ids))], 'id', $row['id']);
        }
    }
}
