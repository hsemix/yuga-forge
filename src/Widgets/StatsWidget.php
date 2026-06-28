<?php

namespace Yuga\Forge\Widgets;

/**
 * A row of stat cards (label, big value, change indicator). Subclasses (or
 * an anonymous class) implement stats(), returning entries shaped like:
 * ['label' => ..., 'value' => ..., 'change' => ..., 'tone' => 'success'|
 * 'info'|'warning'|'primary', 'icon' => ...].
 */
abstract class StatsWidget extends Widget
{
    protected const TONE_CLASSES = [
        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'info' => 'bg-azure-100 text-azure-700 dark:bg-azure-500/15 dark:text-azure-300',
        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'primary' => 'bg-azure-100 text-azure-700 dark:bg-azure-500/15 dark:text-azure-300',
    ];

    /**
     * @return array<int, array{label: string, value: string, change?: string, tone?: string, icon?: string}>
     */
    abstract protected function stats(): array;

    public function render(): string
    {
        $html = '<section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Stats">';

        foreach ($this->stats() as $stat) {
            $html .= $this->renderStat($stat);
        }

        return $html . '</section>';
    }

    protected function renderStat(array $stat): string
    {
        $tone = self::TONE_CLASSES[$stat['tone'] ?? 'primary'] ?? self::TONE_CLASSES['primary'];

        return '<article class="flex min-h-32 gap-3.5 rounded-lg border border-slate-200 bg-white p-4 shadow-lg shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">'
            . '<div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg font-extrabold ' . $tone . '">' . $this->escape($stat['icon'] ?? '') . '</div>'
            . '<div class="min-w-0">'
            . '<span class="block text-sm font-bold text-slate-500 dark:text-slate-400">' . $this->escape($stat['label'] ?? '') . '</span>'
            . '<strong class="mt-2 block break-words text-3xl leading-none text-slate-950 dark:text-white">' . $this->escape($stat['value'] ?? '') . '</strong>'
            . (isset($stat['change']) ? '<small class="mt-2 block leading-5 text-slate-500 dark:text-slate-400">' . $this->escape($stat['change']) . ' from previous period</small>' : '')
            . '</div></article>';
    }
}
