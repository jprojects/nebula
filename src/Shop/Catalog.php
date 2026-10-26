<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;

/**
 * Lectura del catálogo para la tienda: categorías, listados y fichas de producto ya con el precio del grupo
 * del cliente y las promociones automáticas.
 */
final class Catalog
{
    public const SORTS = ['featured', 'newest', 'price_asc', 'price_desc', 'name'];

    private ?array $categories = null;

    public function __construct(
        private readonly Database $db,
        private readonly Pricing $pricing,
        private readonly Promotions $promotions,
    ) {
    }

    // ---------------------------------------------------------------- categorías

    /** @return array<int,array> categorías activas por id, ordenadas */
    public function categories(): array
    {
        if ($this->categories === null) {
            $rows = $this->db->fetchAll('SELECT * FROM categories WHERE active = 1 ORDER BY sort, name');
            $this->categories = [];
            foreach ($rows as $r) {
                $r['id'] = (int) $r['id'];
                $r['parent_id'] = $r['parent_id'] !== null ? (int) $r['parent_id'] : null;
                $this->categories[$r['id']] = $r;
            }
        }
        return $this->categories;
    }

    /** Árbol para el menú: [{...categoría, children: [...]}] (solo las de "in_menu" y con padre visible). */
    public function menuTree(): array
    {
        $byParent = [];
        foreach ($this->categories() as $c) {
            if ($c['in_menu']) {
                $byParent[$c['parent_id'] ?? 0][] = $c;
            }
        }
        $build = function (int $parent, int $depth) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $c) {
                $c['children'] = $depth < 3 ? $build($c['id'], $depth + 1) : [];
                $out[] = $c;
            }
            return $out;
        };
        return $build(0, 1);
    }

    public function categoryBySlug(string $slug): ?array
    {
        foreach ($this->categories() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }
        return null;
    }

    /** Categoría y sus antecesores, de la raíz a ella. */
    public function categoryPath(int $id): array
    {
        $path = [];
        $all = $this->categories();
        for ($i = 0; $i < 10 && isset($all[$id]); $i++) {
            array_unshift($path, $all[$id]);
            if ($all[$id]['parent_id'] === null) {
                break;
            }
            $id = $all[$id]['parent_id'];
        }
        return $path;
    }

    /** @return list<int> la categoría y todas sus descendientes activas */
    public function descendants(int $id): array
    {
        $ids = [$id];
        $all = $this->categories();
        for ($i = 0; $i < count($ids) && $i < 500; $i++) {
            foreach ($all as $c) {
                if ($c['parent_id'] === $ids[$i] && !in_array($c['id'], $ids, true)) {
                    $ids[] = $c['id'];
                }
            }
        }
        return $ids;
    }

    public function children(int $id): array
    {
        return array_values(array_filter($this->categories(), fn ($c) => $c['parent_id'] === $id));
    }

    // ---------------------------------------------------------------- productos

    /**
     * Listado paginado.
     * @param array{category?:?int,q?:?string,sort?:string,page?:int,size?:int,featured?:bool,ids?:list<int>,exclude?:int} $opts
     * @return array{items:list<array>,total:int,page:int,pages:int,size:int}
     */
    public function listing(array $opts, array $group): array
    {
        $where = ['p.active = 1'];
        $params = [];
        if (!empty($opts['category'])) {
            $ids = $this->descendants((int) $opts['category']);
            $where[] = 'EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ('
                . implode(',', array_fill(0, count($ids), '?')) . '))';
            array_push($params, ...$ids);
        }
        if (isset($opts['q']) && trim((string) $opts['q']) !== '') {
            foreach (array_slice(preg_split('/\s+/', trim((string) $opts['q'])) ?: [], 0, 5) as $word) {
                $like = '%' . addcslashes($word, '%_\\') . '%';
                $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.summary LIKE ?)';
                array_push($params, $like, $like, $like);
            }
        }
        if (!empty($opts['featured'])) {
            $where[] = 'p.featured = 1';
        }
        if (!empty($opts['ids'])) {
            $ids = array_map('intval', $opts['ids']);
            $where[] = 'p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...$ids);
        }
        if (!empty($opts['exclude'])) {
            $where[] = 'p.id <> ?';
            $params[] = (int) $opts['exclude'];
        }
        $sql = ' FROM products p WHERE ' . implode(' AND ', $where);
        $total = (int) $this->db->fetchValue('SELECT COUNT(*)' . $sql, $params);

        $size = max(1, min(60, (int) ($opts['size'] ?? 12)));
        $pages = max(1, (int) ceil($total / $size));
        $page = max(1, min($pages, (int) ($opts['page'] ?? 1)));

        [$priceExpr, $priceParams] = $this->pricing->sqlPrice($group);
        $order = match ($opts['sort'] ?? 'featured') {
            'newest' => 'p.created_at DESC, p.id DESC',
            'price_asc' => "$priceExpr ASC, p.id",
            'price_desc' => "$priceExpr DESC, p.id",
            'name' => 'p.name ASC',
            default => 'p.featured DESC, p.sort ASC, p.created_at DESC',
        };
        $orderParams = in_array($opts['sort'] ?? '', ['price_asc', 'price_desc'], true) ? $priceParams : [];
        $rows = $this->db->fetchAll(
            'SELECT p.*' . $sql . " ORDER BY $order LIMIT $size OFFSET " . (($page - 1) * $size),
            [...$params, ...$orderParams]
        );
        $this->pricing->preload(array_column($rows, 'id'), (int) $group['id']);
        return [
            'items' => array_map(fn ($r) => $this->present($r, $group), $rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'size' => $size,
        ];
    }

    public function productBySlug(string $slug, array $group): ?array
    {
        $row = $this->db->fetch('SELECT * FROM products WHERE slug = ? AND active = 1', [$slug]);
        return $row ? $this->present($row, $group, true) : null;
    }

    /** Fila de products activa (para el carrito). */
    public function productRow(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM products WHERE id = ? AND active = 1', [$id]);
    }

    /**
     * Producto listo para la plantilla y el JSON de la página.
     * Imágenes como ids de media; la vista las convierte en URLs.
     */
    public function present(array $row, array $group, bool $full = false): array
    {
        $id = (int) $row['id'];
        $categories = Text::ids($row['categories']);
        $images = Text::ids($row['images']);
        $colors = [];
        foreach (self::colors($row) as $c) {
            $price = $this->pricing->unitPrice($row, $c, 1, $group);
            $promo = $this->promotions->displayPrice($id, $categories, $price, (int) $group['id']);
            $colors[] = $c + ['price' => $promo['price'], 'compare' => self::compare($row, $price, $promo['price'])];
        }
        $price = $this->pricing->unitPrice($row, null, 1, $group);
        $promo = $this->promotions->displayPrice($id, $categories, $price, (int) $group['id']);
        $cover = $images[0] ?? null;
        foreach ($colors as $c) {
            $cover ??= $c['images'][0] ?? null;
        }
        $stock = $row['stock'] !== null ? (int) $row['stock'] : null;
        $out = [
            'id' => $id,
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'sku' => $row['sku'],
            'summary' => $row['summary'],
            'price' => $promo['price'],
            'compare' => self::compare($row, $price, $promo['price']),
            'promo' => $promo['label'],
            'from' => $colors && count(array_unique(array_column($colors, 'price'))) > 1, // precio "desde"
            'cover' => $cover,
            'images' => $images,
            'colors' => $colors,
            'stock' => $stock,
            'in_stock' => $stock === null || $stock > 0,
            'featured' => (bool) $row['featured'],
            'categories' => $categories,
            'tax_rate' => (float) $row['tax_rate'],
        ];
        if ($colors) {
            $out['price'] = min(array_column($colors, 'price'));
        }
        if ($full) {
            $out += [
                'description' => (string) ($row['description'] ?? ''),
                'weight' => (float) $row['weight'],
                'meta_title' => $row['meta_title'],
                'meta_description' => $row['meta_description'],
                'tiers' => array_reverse($this->pricing->tiers($id, (int) $group['id'])),
            ];
        }
        return $out;
    }

    /**
     * Colores de una fila de products: [{code, name, hex, images: [ids], sku, price_delta}].
     * @return list<array{code:string,name:string,hex:?string,images:list<int>,sku:?string,price_delta:float}>
     */
    public static function colors(array $row): array
    {
        $out = [];
        foreach (Text::json($row['colors'] ?? null) as $c) {
            if (!is_array($c) || trim((string) ($c['name'] ?? '')) === '') {
                continue;
            }
            $code = trim((string) ($c['code'] ?? '')) ?: Text::slug((string) $c['name'], 60);
            $hex = is_string($c['hex'] ?? null) && preg_match('/^#[0-9a-f]{3,8}$/i', $c['hex']) ? $c['hex'] : null;
            $out[] = [
                'code' => $code,
                'name' => trim((string) $c['name']),
                'hex' => $hex,
                'images' => Text::ids($c['images'] ?? []),
                'sku' => isset($c['sku']) && trim((string) $c['sku']) !== '' ? trim((string) $c['sku']) : null,
                'price_delta' => (float) ($c['price_delta'] ?? 0),
            ];
        }
        return $out;
    }

    public static function color(array $row, ?string $code): ?array
    {
        foreach (self::colors($row) as $c) {
            if ($c['code'] === $code) {
                return $c;
            }
        }
        return null;
    }

    /** Precio tachado: el anterior del producto o el de antes de la promoción, si es mayor que el final. */
    private static function compare(array $row, float $price, float $final): ?float
    {
        $compare = $row['compare_price'] !== null ? (float) $row['compare_price'] : null;
        if ($final < $price) {
            $compare = max($compare ?? 0, $price);
        }
        return $compare !== null && $compare > $final ? Money::round($compare) : null;
    }
}
