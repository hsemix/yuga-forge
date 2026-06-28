<?php

namespace Yuga\Forge\Fields;

/**
 * A bare <input type="hidden"> - no label, no row in the "Details" view.
 * Still goes through validation/dehydrate/hydrate like any other field;
 * it's just never shown. Useful for a value the form needs to carry along
 * (a parent record's key, a computed default) without exposing an input
 * for it.
 */
class Hidden extends Field
{
    public function isHiddenField(): bool
    {
        return true;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        return '<input type="hidden" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '" ylc:model="' . $this->modelAttr() . '">';
    }

    public function render(mixed $value, ?string $error): string
    {
        return $this->renderInput($value, $error);
    }
}
