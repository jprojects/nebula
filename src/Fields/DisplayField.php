<?php
declare(strict_types=1);

namespace Nebula\Fields;

/**
 * Bloque de solo lectura en la ficha con lo que calcula un "compute" de los hooks (líneas de un pedido,
 * historial…). Con "template", el valor (cualquier array) se pinta con ese parcial de la plantilla, que tiene
 * los helpers de siempre ($this->t() con los textos del módulo); sin él, el valor es HTML ya escapado.
 *
 *   "lines": { "type": "display", "compute": "orderLines", "template": "fields/order_lines" }
 */
final class DisplayField extends Field
{
    public function isReadonly(): bool
    {
        return true;
    }

    public function shouldSave(mixed $value, bool $isNew): bool
    {
        return false;
    }

    public function render(mixed $value, array $opts = []): string
    {
        $template = $this->def['template'] ?? null;
        $html = is_string($template) ? $this->ctx->render($template, ['value' => $value, 'field' => $this]) : (string) $value;
        return '<div class="nb-display" id="' . htmlspecialchars($this->inputId(), ENT_QUOTES) . '">' . $html . '</div>';
    }

    public function format(mixed $value): string
    {
        return is_string($value) ? trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }
}
