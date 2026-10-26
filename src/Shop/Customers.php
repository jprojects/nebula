<?php
declare(strict_types=1);

namespace Nebula\Shop;

use Nebula\Core\Database;
use Nebula\Core\Log;
use Nebula\Core\Session;

/**
 * Clientes de la tienda (tabla customers): cuenta, acceso y recuperación de contraseña.
 * Son independientes de los usuarios de la gestión (tabla users).
 */
final class Customers
{
    private const SESSION = 'nb_customer';
    private ?array $current = null;
    private bool $loaded = false;

    public function __construct(private readonly Database $db)
    {
    }

    /** Cliente con la sesión abierta (activo), o null. */
    public function current(): ?array
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $id = (int) Session::get(self::SESSION, 0);
            $row = $id > 0 ? $this->db->fetch('SELECT * FROM customers WHERE id = ? AND active = 1', [$id]) : null;
            if ($id > 0 && $row === null) {
                Session::remove(self::SESSION);
            }
            $this->current = $row;
        }
        return $this->current;
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM customers WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /**
     * @throws ShopException login_invalid | login_locked
     */
    public function login(string $email, string $password): array
    {
        $row = $this->findByEmail($email);
        if ($row === null || $row['password_hash'] === null || !$row['active']) {
            password_verify($password, password_hash('nebula-dummy', PASSWORD_DEFAULT)); // mismo tiempo de respuesta
            throw new ShopException('login_invalid');
        }
        if ($row['locked_until'] !== null && strtotime((string) $row['locked_until']) > time()) {
            throw new ShopException('login_locked');
        }
        if (!password_verify($password, (string) $row['password_hash'])) {
            $fails = (int) $row['failed_logins'] + 1;
            $max = max(1, (int) ShopConfig::get('shop.login_max_attempts', 5));
            $this->db->update('customers', [
                'failed_logins' => $fails >= $max ? 0 : $fails,
                'locked_until' => $fails >= $max ? date('Y-m-d H:i:s', time() + (int) ShopConfig::get('shop.login_lockout', 300)) : null,
            ], 'id', $row['id']);
            throw new ShopException($fails >= $max ? 'login_locked' : 'login_invalid');
        }
        $update = ['failed_logins' => 0, 'locked_until' => null, 'last_login' => date('Y-m-d H:i:s')];
        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
            $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $this->db->update('customers', $update, 'id', $row['id']);
        $this->startSession((int) $row['id']);
        return $row;
    }

    public function logout(): void
    {
        Session::remove(self::SESSION);
        Session::regenerate();
        $this->current = null;
    }

    /**
     * Alta. Si el email ya existe sin contraseña (compró sin cuenta), se completa esa ficha.
     * @throws ShopException email_taken
     */
    public function register(array $data, int $defaultGroupId, string $locale): array
    {
        $email = mb_strtolower(trim((string) $data['email']));
        $existing = $this->findByEmail($email);
        if ($existing !== null && $existing['password_hash'] !== null) {
            throw new ShopException('email_taken');
        }
        $values = [
            'name' => mb_substr(trim((string) $data['name']), 0, 150),
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'phone' => self::nullable($data['phone'] ?? null, 30),
            'company' => self::nullable($data['company'] ?? null, 150),
            'vat_id' => self::nullable($data['vat_id'] ?? null, 30),
            'accepts_marketing' => !empty($data['accepts_marketing']) ? 1 : 0,
            'locale' => $locale,
        ];
        if ($existing !== null) {
            $this->db->update('customers', $values, 'id', $existing['id']);
            $id = (int) $existing['id'];
        } else {
            $id = (int) $this->db->insert('customers', $values + ['email' => $email, 'group_id' => $defaultGroupId ?: null]);
        }
        Log::channel('shop')->info('Cliente registrado {id}', ['id' => $id]);
        $this->startSession($id);
        return (array) $this->find($id);
    }

    /** Ficha del cliente de un pedido sin cuenta (para agrupar sus pedidos si luego se registra). */
    public function guest(string $email, string $name, ?string $phone, int $defaultGroupId, string $locale): int
    {
        $existing = $this->findByEmail($email);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return (int) $this->db->insert('customers', [
            'email' => mb_strtolower(trim($email)),
            'name' => mb_substr($name, 0, 150),
            'phone' => self::nullable($phone, 30),
            'group_id' => $defaultGroupId ?: null,
            'locale' => $locale,
        ]);
    }

    public function updateProfile(int $id, array $data): void
    {
        $values = [
            'name' => mb_substr(trim((string) $data['name']), 0, 150),
            'phone' => self::nullable($data['phone'] ?? null, 30),
            'company' => self::nullable($data['company'] ?? null, 150),
            'vat_id' => self::nullable($data['vat_id'] ?? null, 30),
            'accepts_marketing' => !empty($data['accepts_marketing']) ? 1 : 0,
        ];
        if (!empty($data['password'])) {
            $values['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        $this->db->update('customers', $values, 'id', $id);
        $this->loaded = false;
    }

    /** @return list<array<string,string>> */
    public function addresses(array $customer): array
    {
        return array_values(array_filter(Text::json($customer['addresses'] ?? null), 'is_array'));
    }

    public function saveAddresses(int $id, array $addresses): void
    {
        $this->db->update('customers', ['addresses' => json_encode(array_values($addresses), JSON_UNESCAPED_UNICODE)], 'id', $id);
        $this->loaded = false;
    }

    /** Guarda la dirección de un pedido en la libreta del cliente si no la tenía ya. */
    public function rememberAddress(int $id, array $address): void
    {
        $customer = $this->find($id);
        if ($customer === null) {
            return;
        }
        $list = $this->addresses($customer);
        $fingerprint = fn (array $a) => mb_strtolower(implode('|', [$a['address'] ?? '', $a['postcode'] ?? '', $a['country'] ?? '']));
        foreach ($list as $a) {
            if ($fingerprint($a) === $fingerprint($address)) {
                return;
            }
        }
        $list[] = $address;
        $this->saveAddresses($id, array_slice($list, -10));
    }

    // ---------------------------------------------------------------- recuperar contraseña

    /** Crea un enlace de un solo uso (1 h). Devuelve el token en claro, o null si el email no tiene cuenta. */
    public function createResetToken(string $email): ?array
    {
        $row = $this->findByEmail($email);
        if ($row === null || !$row['active'] || $row['password_hash'] === null) {
            return null;
        }
        $recent = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM customer_tokens WHERE customer_id = ? AND type = 'reset' AND created_at > ?",
            [$row['id'], date('Y-m-d H:i:s', time() - 3600)]
        );
        if ($recent >= 3) {
            return null;
        }
        $token = bin2hex(random_bytes(24));
        $this->db->insert('customer_tokens', [
            'customer_id' => $row['id'],
            'type' => 'reset',
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        return ['customer' => $row, 'token' => $token];
    }

    public function validResetToken(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) {
            return null;
        }
        return $this->db->fetch(
            "SELECT * FROM customer_tokens WHERE token_hash = ? AND type = 'reset' AND used_at IS NULL AND expires_at > ?",
            [hash('sha256', $token), date('Y-m-d H:i:s')]
        );
    }

    public function resetPassword(string $token, string $password): bool
    {
        $row = $this->validResetToken($token);
        if ($row === null) {
            return false;
        }
        $this->db->update('customer_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id', $row['id']);
        $this->db->update('customers', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'failed_logins' => 0, 'locked_until' => null],
            'id', $row['customer_id']);
        $this->startSession((int) $row['customer_id']);
        return true;
    }

    private function startSession(int $id): void
    {
        Session::regenerate();
        Session::set(self::SESSION, $id);
        $this->loaded = false;
    }

    private static function nullable(mixed $v, int $max): ?string
    {
        $v = is_string($v) ? trim($v) : '';
        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
