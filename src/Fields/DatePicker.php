<?php

namespace Yuga\Forge\Fields;

class DatePicker extends Field
{
    protected bool $withTime = false;

    public function withTime(): static
    {
        $this->withTime = true;

        return $this;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $type = $this->withTime ? 'datetime-local' : 'date';

        return '<input type="' . $type . '" class="' . static::inputClass() . '" value="' . $escape($value) . '" ylc:model="' . $this->modelAttr() . '">';
    }
}
