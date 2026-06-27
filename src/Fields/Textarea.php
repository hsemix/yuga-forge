<?php

namespace Yuga\Forge\Fields;

class Textarea extends Field
{
    protected int $rows = 3;

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        return '<textarea rows="' . $this->rows . '" class="' . static::inputClass() . '" ylc:model="' . $this->modelAttr() . '">' . $escape($value) . '</textarea>';
    }
}
