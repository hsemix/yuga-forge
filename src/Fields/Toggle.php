<?php

namespace Yuga\Forge\Fields;

class Toggle extends Field
{
    public function renderInput(mixed $value, ?string $error): string
    {
        $checked = $value ? ' checked' : '';

        return '<input type="checkbox" class="h-5 w-5 rounded border-slate-300 text-azure-600 focus:ring-azure-500" ylc:model="' . $this->modelAttr() . '"' . $checked . '>';
    }

    public function render(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $label = $escape($this->getLabel());
        $input = $this->renderInput($value, $error);
        $errorHtml = $error
            ? '<small class="font-medium text-red-600">' . $escape($error) . '</small>'
            : '';

        return <<<HTML
            <label class="flex items-center gap-2.5">
                {$input}
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{$label}</span>
            </label>
            {$errorHtml}
            HTML;
    }
}
