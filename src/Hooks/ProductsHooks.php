<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\Action;
use Nebula\Core\ActionResult;
use Nebula\Core\ModuleHooks;
use Nebula\Core\User;
use Nebula\Shop\Text;
use Nebula\Shop\Urls;

/**
 * Productos: slug automático, código estable de cada color (el carrito y los pedidos lo usan) y copia de las
 * categorías y los precios por grupo a sus tablas (product_categories, product_prices) para consultar la tienda.
 */
final class ProductsHooks extends ModuleHooks
{
    public function beforeSave(array &$data, ?string $id, ?array $existing, User $user): ?string
    {
        if (array_key_exists('slug', $data) || $id === null) {
            $base = trim((string) ($data['slug'] ?? '')) !== '' ? Text::slug((string) $data['slug']) : Text::slug((string) ($data['name'] ?? $existing['name'] ?? ''));
            $data['slug'] = Text::uniqueSlug($this->db, 'products', $base, $id);
        }
        if (array_key_exists('colors', $data)) {
            $data['colors'] = json_encode($this->colorCodes(Text::json($data['colors'])), JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('prices', $data)) {
            // Una sola fila por grupo y cantidad mínima
            $seen = [];
            foreach (Text::json($data['prices']) as $p) {
                $key = ($p['group_id'] ?? '') . ':' . max(1, (int) ($p['min_qty'] ?? 1));
                if (isset($seen[$key])) {
                    return 'validation.prices_duplicated';
                }
                $seen[$key] = true;
            }
        }
        return null;
    }

    public function afterSave(string $id, array $data, bool $isNew, User $user): void
    {
        if (array_key_exists('categories', $data)) {
            $this->db->query('DELETE FROM product_categories WHERE product_id = ?', [$id]);
            foreach (Text::ids($data['categories']) as $cat) {
                $this->db->query('INSERT IGNORE INTO product_categories (product_id, category_id)
                                  SELECT ?, id FROM categories WHERE id = ?', [$id, $cat]);
            }
        }
        if (array_key_exists('prices', $data)) {
            self::syncPrices($this->db, (int) $id, Text::json($data['prices']));
        }
    }

    /** Copia products.prices a product_prices. */
    public static function syncPrices(\Nebula\Core\Database $db, int $productId, array $prices): void
    {
        $db->query('DELETE FROM product_prices WHERE product_id = ?', [$productId]);
        foreach ($prices as $p) {
            if (!is_array($p) || !is_numeric($p['group_id'] ?? null) || !is_numeric($p['price'] ?? null)) {
                continue;
            }
            $db->query('INSERT IGNORE INTO product_prices (product_id, group_id, min_qty, price)
                        SELECT ?, id, ?, ? FROM customer_groups WHERE id = ?',
                [$productId, max(1, (int) ($p['min_qty'] ?? 1)), round((float) $p['price'], 2), (int) $p['group_id']]);
        }
    }

    // ---------------------------------------------------------------- acciones

    public function duplicate(Action $a): ActionResult
    {
        $row = $this->db->fetch('SELECT * FROM products WHERE id = ?', [$a->id]);
        if ($row === null) {
            return ActionResult::error($a->t('flash.not_found'));
        }
        unset($row['id'], $row['created_at'], $row['updated_at']);
        $row['name'] .= ' (' . $a->t('flash.copy') . ')';
        $row['slug'] = Text::uniqueSlug($this->db, 'products', Text::slug($row['name']), null);
        $row['active'] = 0;
        $newId = $this->db->insert('products', $row);
        $this->afterSave($newId, ['categories' => $row['categories'], 'prices' => $row['prices']], true, $a->user);
        return ActionResult::redirect($a->editUrl($newId), $a->t('flash.duplicated'));
    }

    public function viewInShop(Action $a): ActionResult
    {
        $slug = (string) $this->db->fetchValue('SELECT slug FROM products WHERE id = ?', [$a->id]);
        return ActionResult::redirect(Urls::fromConfig()->abs('/p/' . rawurlencode($slug)));
    }

    // ---------------------------------------------------------------- internos

    /**
     * Cada color conserva su código aunque se cambie el nombre (un carrito abierto o un pedido lo referencian).
     * Los nuevos lo toman del nombre, sin repetir dentro del producto.
     */
    private function colorCodes(array $colors): array
    {
        $used = [];
        $out = [];
        foreach ($colors as $c) {
            if (!is_array($c)) {
                continue;
            }
            $code = trim((string) ($c['code'] ?? ''));
            if ($code === '' || isset($used[$code])) {
                $base = Text::slug((string) ($c['name'] ?? 'color'), 50);
                $code = $base;
                for ($i = 2; isset($used[$code]); $i++) {
                    $code = $base . '-' . $i;
                }
            }
            $used[$code] = true;
            $c['code'] = $code;
            $out[] = $c;
        }
        return $out;
    }
}
