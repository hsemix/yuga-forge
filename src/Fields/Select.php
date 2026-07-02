<?php

namespace Yuga\Forge\Fields;

class Select extends Field
{
    protected array $options = [];
    protected ?string $relation = null;
    protected ?string $titleColumn = null;
    protected int $searchLimit = 20;

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Turns this Select into a server-side search-as-you-type combobox
     * against a related model's table, instead of a fixed list of
     * options - for picking one record out of a table too large to dump
     * into a <select> (e.g. assigning a Customer to an Order). Always
     * searchable once set; there's no non-searchable relationship mode -
     * a small fixed list is what options() is for. Resource owns the
     * actual rendering/querying for this mode (see
     * Resource::renderFormField()), since it needs DB access this Field
     * doesn't have.
     */
    public function relationship(string $relation, string $titleColumn, int $limit = 20): static
    {
        $this->relation = $relation;
        $this->titleColumn = $titleColumn;
        $this->searchLimit = $limit;

        return $this;
    }

    public function isRelationship(): bool
    {
        return $this->relation !== null;
    }

    public function getRelation(): ?string
    {
        return $this->relation;
    }

    public function getTitleColumn(): ?string
    {
        return $this->titleColumn;
    }

    public function getSearchLimit(): int
    {
        return $this->searchLimit;
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

        return '<select class="h-10 ' . static::inputClass() . '" ylc:model="' . $this->modelAttr() . '">' . $optionsHtml . '</select>';
    }

    /**
     * Without this, the "Details" view would show the raw stored value
     * (e.g. "search") instead of its label ("Search engine") for any Select
     * whose option keys/labels actually differ - a no-op for the existing
     * resources in this app, since their options happen to use the same
     * string for both (e.g. "Active" => "Active"), but a real gap
     * otherwise. Same lookup MultiSelect's renderDisplay() already does.
     */
    public function renderDisplay(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '&mdash;';
        }

        return htmlspecialchars((string) ($this->options[$value] ?? $value), ENT_QUOTES, 'UTF-8');
    }
}
