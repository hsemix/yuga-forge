<?php

namespace Yuga\Forge\Columns;

class BadgeColumn extends Column
{
    protected array $colors = [];

    protected const CLASS_MAP = [
        'slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
        'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        'azure' => 'bg-azure-100 text-azure-700 dark:bg-azure-500/15 dark:text-azure-300',
    ];

    /**
     * @param array<string,string> $colors Maps a column value to one of: slate, emerald, amber, red, violet, azure.
     */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function renderCell(array $record): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $value = $this->value($record);
        $color = $this->colors[$value] ?? 'slate';
        $classes = static::CLASS_MAP[$color] ?? static::CLASS_MAP['slate'];

        return '<span class="rounded-full px-2.5 py-1 text-xs font-bold ' . $classes . '">' . $escape($value) . '</span>';
    }
}
