<?php

namespace Yuga\Forge\Fields;

/**
 * A radio group - the simpler alternative to Select for a handful of
 * options where seeing them all at once (no dropdown to open) is worth the
 * extra vertical space. ylc:model already special-cases type="radio"
 * (getInputValue() in ylc-live-plugin.js returns the checked one's value,
 * or null) - no new JS needed, same as Toggle/Select already work without it.
 */
class Radio extends Field
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
        $html = '<div class="grid gap-2">';

        foreach ($this->options as $optionValue => $optionLabel) {
            if (is_int($optionValue)) {
                $optionValue = $optionLabel;
            }

            $checked = (string) $value === (string) $optionValue ? ' checked' : '';

            $html .= '<label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">'
                . '<input type="radio" class="h-4 w-4 border-slate-300 text-azure-600 focus:ring-azure-500" name="' . $escape($this->getName()) . '" value="' . $escape($optionValue) . '"' . $checked . ' ylc:model="' . $this->modelAttr() . '">'
                . $escape($optionLabel) . '</label>';
        }

        return $html . '</div>';
    }

    /** Same lookup MultiSelect's/Select's renderDisplay() already does. */
    public function renderDisplay(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '&mdash;';
        }

        return htmlspecialchars((string) ($this->options[$value] ?? $value), ENT_QUOTES, 'UTF-8');
    }
}
