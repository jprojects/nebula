<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\ModuleHooks;
use Nebula\Core\User;

/** Promociones: el cupón se guarda en mayúsculas (vacío = automática) y se calcula su estado para el listado. */
final class PromotionsHooks extends ModuleHooks
{
    public function beforeSave(array &$data, ?string $id, ?array $existing, User $user): ?string
    {
        if (array_key_exists('code', $data)) {
            $code = mb_strtoupper(trim((string) $data['code']));
            $data['code'] = $code !== '' ? $code : null;
        }
        $type = $data['type'] ?? $existing['type'] ?? 'percent';
        $value = (float) ($data['value'] ?? $existing['value'] ?? 0);
        if ($type === 'percent' && ($value <= 0 || $value > 100)) {
            return 'validation.percent';
        }
        if ($type === 'fixed' && $value <= 0) {
            return 'validation.value';
        }
        $starts = $data['starts_at'] ?? $existing['starts_at'] ?? null;
        $ends = $data['ends_at'] ?? $existing['ends_at'] ?? null;
        if ($starts && $ends && strtotime((string) $ends) < strtotime((string) $starts)) {
            return 'validation.dates';
        }
        return null;
    }

    /** running | scheduled | ended | off | exhausted */
    public function state(array $row): string
    {
        $now = time();
        return match (true) {
            !$row['active'] => 'off',
            $row['max_uses'] !== null && (int) $row['uses'] >= (int) $row['max_uses'] => 'exhausted',
            $row['starts_at'] !== null && strtotime((string) $row['starts_at']) > $now => 'scheduled',
            $row['ends_at'] !== null && strtotime((string) $row['ends_at']) < $now => 'ended',
            default => 'running',
        };
    }
}
