<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\ModuleHooks;
use Nebula\Core\User;

/**
 * Grupos de clientes (tarifas): solo uno es el de por defecto, y el de por defecto no se puede borrar.
 * Al borrar uno, sus clientes pasan al de por defecto; sus precios (product_prices) se borran por la clave foránea.
 */
final class CustomerGroupsHooks extends ModuleHooks
{
    public function afterSave(string $id, array $data, bool $isNew, User $user): void
    {
        if (!empty($data['is_default'])) {
            $this->db->query('UPDATE customer_groups SET is_default = 0 WHERE id <> ?', [$id]);
        }
    }

    public function beforeDelete(string $id, User $user): ?string
    {
        return (int) $this->db->fetchValue('SELECT is_default FROM customer_groups WHERE id = ?', [$id]) === 1 ? 'validation.default_group' : null;
    }

    public function afterDelete(string $id, User $user): void
    {
        $default = $this->db->fetchValue('SELECT id FROM customer_groups WHERE is_default = 1 LIMIT 1');
        $this->db->query('UPDATE customers SET group_id = ? WHERE group_id = ?', [$default, $id]);
    }
}
