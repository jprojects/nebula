<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\ModuleHooks;
use Nebula\Core\User;
use Nebula\Shop\Payments\PaymentMethods;
use Nebula\Shop\ShopConfig;

/** Formas de pago: la pasarela tiene que existir en config 'payments.gateways' y se indica si tiene las claves puestas. */
final class PaymentMethodsHooks extends ModuleHooks
{
    private ?PaymentMethods $methods = null;

    public function beforeSave(array &$data, ?string $id, ?array $existing, User $user): ?string
    {
        if (isset($data['gateway']) && $this->methods()->gateway((string) $data['gateway']) === null) {
            return 'validation.gateway';
        }
        return null;
    }

    public function configured(array $row): string
    {
        $gateway = $this->methods()->gateway((string) $row['gateway']);
        return $gateway !== null && $gateway->available() ? 'yes' : 'no';
    }

    private function methods(): PaymentMethods
    {
        return $this->methods ??= new PaymentMethods($this->db, (array) ShopConfig::get('payments', []));
    }
}
