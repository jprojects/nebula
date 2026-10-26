<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\ModuleHooks;
use Nebula\Core\User;
use Nebula\Shop\Text;

/** Formas de envío: países de las reglas en mayúsculas y sin espacios ("es, pt" → "ES,PT"). */
final class ShippingHooks extends ModuleHooks
{
    public function beforeSave(array &$data, ?string $id, ?array $existing, User $user): ?string
    {
        if (!array_key_exists('rules', $data)) {
            return null;
        }
        $rules = [];
        foreach (Text::json($data['rules']) as $rule) {
            $countries = array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', (string) ($rule['countries'] ?? ''))));
            foreach ($countries as $c) {
                if (!preg_match('/^[A-Z]{2}$/', $c)) {
                    return 'validation.rule_country';
                }
            }
            $rule['countries'] = implode(',', $countries);
            $rules[] = $rule;
        }
        $data['rules'] = json_encode($rules, JSON_UNESCAPED_UNICODE);
        return null;
    }
}
