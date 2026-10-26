<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\ModuleHooks;
use Nebula\Core\User;

/** Clientes: email en minúsculas (es el usuario de acceso) y pedidos sin cliente al borrarlo. */
final class CustomersHooks extends ModuleHooks
{
    public function beforeSave(array &$data, ?string $id, ?array $existing, User $user): ?string
    {
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower(trim((string) $data['email']));
            $dup = $this->db->fetchValue('SELECT id FROM customers WHERE email = ?' . ($id !== null ? ' AND id <> ?' : ''),
                $id !== null ? [$data['email'], $id] : [$data['email']]);
            if ($dup !== null) {
                return 'validation.email_taken';
            }
        }
        if (array_key_exists('locale', $data) && $data['locale'] === '') {
            $data['locale'] = null;
        }
        return null;
    }

    public function afterDelete(string $id, User $user): void
    {
        $this->db->query('UPDATE orders SET customer_id = NULL WHERE customer_id = ?', [$id]);
    }
}
