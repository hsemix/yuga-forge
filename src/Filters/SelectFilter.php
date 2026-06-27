<?php

namespace Yuga\Forge\Filters;

class SelectFilter extends Filter
{
    protected array $options = [];
    protected string $allLabel = 'All';

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function allLabel(string $label): static
    {
        $this->allLabel = $label;

        return $this;
    }

    public function renderInput(mixed $value): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $optionsHtml = '<option value="all"' . ($value === 'all' ? ' selected' : '') . '>' . $escape($this->allLabel) . '</option>';

        foreach ($this->options as $optionValue => $optionLabel) {
            if (is_int($optionValue)) {
                $optionValue = $optionLabel;
            }

            $selected = (string) $value === (string) $optionValue ? ' selected' : '';
            $optionsHtml .= '<option value="' . $escape($optionValue) . '"' . $selected . '>' . $escape($optionLabel) . '</option>';
        }

        $inputClass = 'h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-slate-950 outline-none focus:border-azure-600 focus:ring-4 focus:ring-azure-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-azure-400 dark:focus:ring-azure-500/20';

        return '<select class="' . $inputClass . '" ylc:model="filters.' . $this->name . '">' . $optionsHtml . '</select>';
    }

    public function matches(array $record, mixed $filterValue): bool
    {
        return (string) ($record[$this->name] ?? null) === (string) $filterValue;
    }
}
