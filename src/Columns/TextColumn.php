<?php

namespace Yuga\Forge\Columns;

class TextColumn extends Column
{
    protected ?string $description = null;

    public function description(string $field): static
    {
        $this->description = $field;

        return $this;
    }

    public function renderCell(array $record): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<strong class="text-slate-950 dark:text-white">' . $escape($this->value($record)) . '</strong>';

        $descriptionValue = $this->description ? $this->dig($record, $this->description) : null;

        if ($descriptionValue !== null) {
            $html .= '<small class="block text-slate-500 dark:text-slate-400">' . $escape($descriptionValue) . '</small>';
        }

        return $html;
    }
}
