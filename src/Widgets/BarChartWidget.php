<?php

namespace Yuga\Forge\Widgets;

/**
 * A vertical bar chart. Subclasses implement data(), returning an ordered
 * map of label => numeric value (e.g. ['Mon' => 120, 'Tue' => 80, ...]) -
 * the widget computes relative bar heights itself.
 */
abstract class BarChartWidget extends Widget
{
    protected string $gradientClass = 'bg-gradient-to-b from-azure-400 to-azure-600';

    /** @return array<string, int|float> */
    abstract protected function data(): array;

    /** Overridable trailing badge in the header row, e.g. a date-range label. */
    protected function badge(): ?string
    {
        return null;
    }

    public function render(): string
    {
        $data = $this->data();
        $max = max(1, ...array_values($data ?: [1]));
        $bars = '';

        foreach ($data as $label => $value) {
            $heightClass = $this->heightClass($value ? max(25, (int) round(($value / $max) * 95)) : 25);

            $bars .= '<div class="grid h-full grid-rows-[1fr_auto] gap-2.5 text-center text-sm font-bold text-slate-500 dark:text-slate-400">'
                . '<div class="flex min-h-0 items-end overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">'
                . '<span class="block w-full rounded-t-lg ' . $this->gradientClass . ' ' . $heightClass . '"></span>'
                . '</div><small>' . $this->escape($label) . '</small></div>';
        }

        return '<article class="rounded-lg border border-slate-200 bg-white p-5 shadow-lg shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">'
            . $this->renderHeader($this->badge())
            . '<div class="grid h-72 grid-cols-' . max(1, count($data)) . ' items-end gap-3.5 pt-7" aria-label="' . $this->escape($this->heading() ?? 'Chart') . '">' . $bars . '</div>'
            . '</article>';
    }

    /**
     * Returns one of a fixed set of literal Tailwind classes (not a dynamic
     * "h-[{$value}%]" string) - Tailwind's build only generates CSS for
     * classes it can find as literal text while scanning source files; a
     * class built from a runtime value would never appear in any scanned
     * file and would silently produce no CSS at all.
     */
    protected function heightClass(int $value): string
    {
        return match (true) {
            $value >= 95 => 'h-[95%]',
            $value >= 90 => 'h-[90%]',
            $value >= 85 => 'h-[85%]',
            $value >= 80 => 'h-[80%]',
            $value >= 75 => 'h-[75%]',
            $value >= 70 => 'h-[70%]',
            $value >= 65 => 'h-[65%]',
            $value >= 60 => 'h-[60%]',
            $value >= 55 => 'h-[55%]',
            $value >= 50 => 'h-[50%]',
            $value >= 45 => 'h-[45%]',
            $value >= 40 => 'h-[40%]',
            $value >= 35 => 'h-[35%]',
            $value >= 30 => 'h-[30%]',
            default => 'h-[25%]',
        };
    }
}
