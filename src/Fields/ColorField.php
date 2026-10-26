<?php
declare(strict_types=1);

namespace Nebula\Fields;

/** Color en hexadecimal (#rrggbb) con el selector de color del navegador. */
final class ColorField extends TextField
{
    protected string $inputType = 'color';

    public function fromRequest(array $input): mixed
    {
        $value = strtolower((string) parent::fromRequest($input));
        // El selector siempre envía un color (#000000 por defecto): negro sin tocar = sin color
        return $value === '#000000' && empty($this->def['allowBlack']) ? '' : $value;
    }

    protected function validateValue(mixed $value): array
    {
        return preg_match('/^#[0-9a-f]{6}$/', (string) $value) ? [] : [$this->ctx->t('validation.pattern')];
    }
}
