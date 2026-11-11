<?php
declare(strict_types=1);

namespace Nebula\Hooks;

use Nebula\Core\ModuleHooks;
use Nebula\Core\User;
use Nebula\Shop\Text;

/**
 * Idiomas de la tienda: uno solo es el de por defecto (el de los textos del catálogo), que siempre está activo y no
 * se puede borrar. Si cambia el código de un idioma, sus traducciones lo siguen; si se borra, se borran.
 */
final class ShopLanguagesHooks extends ModuleHooks
{
    private ?string $oldCode = null;

    public function beforeSave(array &$data, ?string $id, ?array $existing, User $user): ?string
    {
        $isDefault = array_key_exists('is_default', $data) ? !empty($data['is_default']) : !empty($existing['is_default']);
        if ($isDefault) {
            $data['active'] = 1;
        }
        if ($existing !== null && !empty($existing['is_default']) && array_key_exists('is_default', $data) && empty($data['is_default'])) {
            return 'validation.keep_default'; // se cambia marcando otro como el de por defecto
        }
        if ($existing !== null && isset($data['code']) && $data['code'] !== $existing['code']) {
            $this->oldCode = (string) $existing['code'];
        }
        return null;
    }

    public function afterSave(string $id, array $data, bool $isNew, User $user): void
    {
        if (!empty($data['is_default'])) {
            $this->db->query('UPDATE shop_languages SET is_default = 0 WHERE id <> ?', [$id]);
        }
        if ($this->oldCode !== null && isset($data['code'])) {
            foreach (['product_translations', 'category_translations', 'customers'] as $table) {
                $this->db->query("UPDATE $table SET locale = ? WHERE locale = ?", [$data['code'], $this->oldCode]);
            }
            foreach (['products', 'categories'] as $table) {
                $this->rewriteJson($table, $this->oldCode, (string) $data['code']);
            }
        }
    }

    public function beforeDelete(string $id, User $user): ?string
    {
        return (int) $this->db->fetchValue('SELECT is_default FROM shop_languages WHERE id = ?', [$id]) === 1 ? 'validation.delete_default' : null;
    }

    public function afterDelete(string $id, User $user): void
    {
        // El código ya no está en la tabla: se borran las traducciones de los idiomas que no existen
        $codes = array_column($this->db->fetchAll('SELECT code FROM shop_languages'), 'code');
        $in = implode(',', array_fill(0, count($codes), '?'));
        foreach (['product_translations', 'category_translations'] as $table) {
            $this->db->query("DELETE FROM $table WHERE locale NOT IN ($in)", $codes);
        }
        $this->db->query("UPDATE customers SET locale = NULL WHERE locale IS NOT NULL AND locale NOT IN ($in)", $codes);
        foreach (['products', 'categories'] as $table) {
            $this->rewriteJson($table, null, null, $codes);
        }
    }

    /** Cambia (o quita, si $to es null) el idioma de las filas del repeater de traducciones. */
    private function rewriteJson(string $table, ?string $from, ?string $to, ?array $keep = null): void
    {
        foreach ($this->db->fetchAll("SELECT id, translations FROM $table WHERE translations IS NOT NULL") as $row) {
            $rows = Text::json($row['translations']);
            $out = [];
            foreach ($rows as $t) {
                $locale = (string) ($t['locale'] ?? '');
                if ($keep !== null && !in_array($locale, $keep, true)) {
                    continue;
                }
                if ($from !== null && $locale === $from) {
                    $t['locale'] = $to;
                }
                $out[] = $t;
            }
            if ($out !== $rows) {
                $this->db->update($table, ['translations' => json_encode($out, JSON_UNESCAPED_UNICODE)], 'id', $row['id']);
            }
        }
    }
}
