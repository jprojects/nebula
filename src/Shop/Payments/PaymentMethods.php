<?php
declare(strict_types=1);

namespace Nebula\Shop\Payments;

use Nebula\Core\Database;
use Nebula\Shop\Money;
use Nebula\Shop\Text;

/**
 * Formas de pago (tabla payment_methods) y sus pasarelas (config 'payments.gateways').
 */
final class PaymentMethods
{
    /** @var array<string,Gateway> */
    private array $gateways = [];

    public function __construct(private readonly Database $db, private readonly array $config)
    {
    }

    /** @return array<string,class-string<Gateway>> código => clase */
    public function gatewayClasses(): array
    {
        $out = [];
        foreach ((array) ($this->config['gateways'] ?? []) as $code => $class) {
            if (is_string($code) && preg_match('/^[a-z0-9_]{1,30}$/', $code) && is_string($class) && is_subclass_of($class, Gateway::class)) {
                $out[$code] = $class;
            }
        }
        return $out;
    }

    public function gateway(string $code): ?Gateway
    {
        if (!isset($this->gateways[$code])) {
            $class = $this->gatewayClasses()[$code] ?? null;
            if ($class === null) {
                return null;
            }
            $this->gateways[$code] = new $class((array) ($this->config[$code] ?? []), $code);
        }
        return $this->gateways[$code];
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM payment_methods WHERE id = ?', [$id]);
    }

    /**
     * Formas de pago que se pueden ofrecer para un importe y un grupo, con su recargo.
     * @return list<array{id:int,gateway:string,name:string,description:?string,fee:float,online:bool}>
     */
    public function available(float $total, int $groupId): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM payment_methods WHERE active = 1 ORDER BY sort, id') as $m) {
            $gateway = $this->gateway((string) $m['gateway']);
            if ($gateway === null || !$gateway->available()) {
                continue;
            }
            $groups = Text::ids($m['group_ids']);
            if ($groups && !in_array($groupId, $groups, true)) {
                continue;
            }
            if (($m['min_total'] !== null && $total < (float) $m['min_total']) || ($m['max_total'] !== null && $total > (float) $m['max_total'])) {
                continue;
            }
            $out[] = [
                'id' => (int) $m['id'],
                'gateway' => (string) $m['gateway'],
                'name' => (string) $m['name'],
                'description' => $m['description'],
                'fee' => self::fee($m, $total),
                'online' => $gateway->online(),
            ];
        }
        return $out;
    }

    /** Recargo de una forma de pago sobre el total del pedido. */
    public static function fee(array $method, float $total): float
    {
        return Money::round((float) $method['fee_fixed'] + $total * (float) $method['fee_percent'] / 100);
    }
}
