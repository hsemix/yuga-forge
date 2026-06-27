<?php

namespace Yuga\Forge\Filters;

class DateRangeFilter extends Filter
{
    protected mixed $default = ['from' => '', 'to' => ''];

    public function renderInput(mixed $value): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $from = $value['from'] ?? '';
        $to = $value['to'] ?? '';
        $inputClass = 'h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-slate-950 outline-none focus:border-azure-600 focus:ring-4 focus:ring-azure-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-azure-400 dark:focus:ring-azure-500/20';

        return '<div class="flex gap-2">'
            . '<input type="date" class="' . $inputClass . '" value="' . $escape($from) . '" ylc:model="filters.' . $this->name . '.from">'
            . '<input type="date" class="' . $inputClass . '" value="' . $escape($to) . '" ylc:model="filters.' . $this->name . '.to">'
            . '</div>';
    }

    public function apply($query, mixed $value): void
    {
        $from = $value['from'] ?? '';
        $to = $value['to'] ?? '';

        if ($from !== '') {
            $query->where($this->name, '>=', $from);
        }

        if ($to !== '') {
            $query->where($this->name, '<=', $to);
        }
    }
}
