<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;

/**
 * Precio de un producto para un grupo de clientes (tarifa) y una cantidad. Todos los precios llevan el IVA incluido.
 *
 *   1. Si el producto tiene precios para el grupo (product_prices), el de mayor cantidad mínima que se alcanza.
 *   2. Si no, el precio general con el descuento general del grupo (customer_groups.discount_percent).
 *   3. Al resultado se le suma el suplemento del color elegido (colors[].price_delta).
 *
 * Las promociones (Promotions) van aparte: se muestran como precio rebajado y en el carrito como descuento.
 */
final class Pricing
{
    /** @var array<int,array<string,mixed>> */
    private array $groups = [];
    private ?array $defaultGroup = null;
    /** @var array<string,list<array{min_qty:int,price:float}>> "producto:grupo" => escalas */
    private array $tiers = [];

    public function __construct(private readonly Database $db)
    {
    }

    /** Grupo por defecto (visitantes y clientes nuevos); si no hay ninguno marcado, uno vacío sin descuento. */
    public function defaultGroup(): array
    {
        if ($this->defaultGroup === null) {
            $row = $this->db->fetch('SELECT * FROM customer_groups WHERE is_default = 1 ORDER BY id LIMIT 1')
                ?? $this->db->fetch('SELECT * FROM customer_groups ORDER BY id LIMIT 1');
            $this->defaultGroup = $row ?? ['id' => 0, 'code' => 'default', 'name' => '', 'discount_percent' => 0, 'is_default' => 1];
        }
        return $this->defaultGroup;
    }

    public function group(?int $id): array
    {
        if ($id === null || $id <= 0) {
            return $this->defaultGroup();
        }
        if (!array_key_exists($id, $this->groups)) {
            $this->groups[$id] = $this->db->fetch('SELECT * FROM customer_groups WHERE id = ?', [$id]);
        }
        return $this->groups[$id] ?? $this->defaultGroup();
    }

    /** Carga de una vez las escalas de varios productos para un grupo (listados). */
    public function preload(array $productIds, int $groupId): void
    {
        $ids = array_values(array_filter(array_map('intval', $productIds), fn ($id) => !isset($this->tiers[$id . ':' . $groupId])));
        if (!$ids || $groupId <= 0) {
            return;
        }
        foreach ($ids as $id) {
            $this->tiers[$id . ':' . $groupId] = [];
        }
        $rows = $this->db->fetchAll(
            'SELECT product_id, min_qty, price FROM product_prices WHERE group_id = ? AND product_id IN ('
                . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY min_qty DESC',
            [$groupId, ...$ids]
        );
        foreach ($rows as $r) {
            $this->tiers[$r['product_id'] . ':' . $groupId][] = ['min_qty' => (int) $r['min_qty'], 'price' => (float) $r['price']];
        }
    }

    /** @return list<array{min_qty:int,price:float}> escalas del grupo, de mayor a menor cantidad */
    public function tiers(int $productId, int $groupId): array
    {
        $this->preload([$productId], $groupId);
        return $this->tiers[$productId . ':' . $groupId] ?? [];
    }

    /**
     * Precio unitario (IVA incluido) antes de promociones.
     * @param array $product fila de products (al menos id y price)
     * @param array|null $color fila de products.colors
     */
    public function unitPrice(array $product, ?array $color, int $qty, array $group): float
    {
        $base = null;
        foreach ($this->tiers((int) $product['id'], (int) $group['id']) as $tier) {
            if ($qty >= $tier['min_qty']) {
                $base = $tier['price'];
                break;
            }
        }
        if ($base === null) {
            $discount = max(0.0, min(100.0, (float) ($group['discount_percent'] ?? 0)));
            $base = (float) $product['price'] * (1 - $discount / 100);
        }
        $delta = $color !== null ? (float) ($color['price_delta'] ?? 0) : 0.0;
        return Money::round(max(0.0, $base + $delta));
    }

    /**
     * Expresión SQL del precio por unidad del grupo (para ordenar listados por precio).
     * Usa el alias "p" de products. Los parámetros van en este orden: [grupo, descuento].
     * @return array{0:string,1:list<mixed>}
     */
    public function sqlPrice(array $group): array
    {
        return [
            '(COALESCE((SELECT pp.price FROM product_prices pp WHERE pp.product_id = p.id AND pp.group_id = ? AND pp.min_qty <= 1
                ORDER BY pp.min_qty DESC LIMIT 1), p.price * (1 - ? / 100)))',
            [(int) $group['id'], (float) ($group['discount_percent'] ?? 0)],
        ];
    }
}
