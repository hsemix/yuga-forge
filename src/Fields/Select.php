<?php

namespace Yuga\Forge\Fields;

class Select extends Field
{
    protected array $options = [];

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $optionsHtml = '';

        foreach ($this->options as $optionValue => $optionLabel) {
            if (is_int($optionValue)) {
                $optionValue = $optionLabel;
            }

            $selected = (string) $value === (string) $optionValue ? ' selected' : '';
            $optionsHtml .= '<option value="' . $escape($optionValue) . '"' . $selected . '>' . $escape($optionLabel) . '</option>';
        }

        return '<select class="' . static::inputClass() . '" ylc:model="' . $this->modelAttr() . '">' . $optionsHtml . '</select>';
    }
}
