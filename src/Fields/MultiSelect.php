<?php

namespace Yuga\Forge\Fields;

/**
 * A multi-value <select multiple>. YLC's ylc:model binding already turns a
 * native multi-select's selected options into a plain array on change
 * (Array.from(el.selectedOptions).map(o => o.value) in ylc-live-plugin.js's
 * getInputValue()) - no special wiring needed beyond the `multiple` attribute.
 */
class MultiSelect extends Field
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
        $selected = is_array($value) ? array_map('strval', $value) : [];

        $optionsHtml = '';

        foreach ($this->options as $optionValue => $optionLabel) {
            if (is_int($optionValue)) {
                $optionValue = $optionLabel;
            }

            $isSelected = in_array((string) $optionValue, $selected, true) ? ' selected' : '';
            $optionsHtml .= '<option value="' . $escape($optionValue) . '"' . $isSelected . '>' . $escape($optionLabel) . '</option>';
        }

        return '<select multiple class="' . static::inputClass() . ' min-h-28" ylc:model="' . $this->modelAttr() . '">' . $optionsHtml . '</select>';
    }

    /**
     * Yuga's column builder has no JSON column type (only text()), so a
     * MultiSelect's natural backing storage is a TEXT column holding a JSON
     * array - serialize automatically rather than making every consumer
     * wire this up by hand via dehydrateUsing()/hydrateUsing().
     */
    public function dehydrate(mixed $value): mixed
    {
        return parent::dehydrate(json_encode(is_array($value) ? $value : []));
    }

    public function hydrate(mixed $value): mixed
    {
        if (is_array($value)) {
            return parent::hydrate($value);
        }

        $decoded = json_decode((string) $value, true);

        return parent::hydrate(is_array($decoded) ? $decoded : []);
    }
}
