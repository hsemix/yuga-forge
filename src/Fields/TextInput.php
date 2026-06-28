<?php

namespace Yuga\Forge\Fields;

class TextInput extends Field
{
    protected string $type = 'text';

    public function email(): static
    {
        $this->type = 'email';
        $this->rules[] = 'email';

        return $this;
    }

    public function number(): static
    {
        $this->type = 'number';

        return $this;
    }

    public function password(): static
    {
        $this->type = 'password';

        return $this;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        return '<input type="' . $this->type . '" class="h-10 ' . static::inputClass() . '" value="' . $escape($value) . '" ylc:model="' . $this->modelAttr() . '">';
    }
}
