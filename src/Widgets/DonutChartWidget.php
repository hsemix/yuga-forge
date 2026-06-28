<?php

namespace Yuga\Forge\Widgets;

/**
 * A donut chart (conic-gradient circle + legend) breaking down rows() by
 * field() against a fixed set of segments(). E.g. order rows by "status"
 * against ['paid' => [...], 'processing' => [...], 'failed' => [...]].
 *
 * The gradient and per-segment widths are inline `style` attributes, not
 * Tailwind classes, so - unlike BarChartWidget's bar heights - there's no
 * CSS-purging concern computing them from a runtime value here.
 */
abstract class DonutChartWidget extends Widget
{
    /** @return array<int, array<string, mixed>> */
    abstract protected function rows(): array;

    abstract protected function field(): string;

    /** @return array<string, array{label: string, class: string, color: string}> */
    abstract protected function segments(): array;

    public function render(): string
    {
        $segments = $this->segments();
        $field = $this->field();
        $counts = array_fill_keys(array_keys($segments), 0);

        foreach ($this->rows() as $row) {
            $value = $row[$field] ?? null;

            if (array_key_exists($value, $counts)) {
                $counts[$value]++;
            }
        }

        $total = array_sum($counts);
        $cursor = 0;
        $gradient = [];
        $legend = '';

        foreach ($segments as $key => $segment) {
            $count = $counts[$key] ?? 0;
            $percent = $total ? (int) round(($count / $total) * 100) : 0;
            $end = $cursor + $percent;

            if ($percent > 0) {
                $gradient[] = "{$segment['color']} {$cursor}% {$end}%";
            }

            $legend .= '<div><div class="flex items-center justify-between gap-3 text-sm font-bold">'
                . '<span class="flex min-w-0 items-center gap-2 text-slate-700 dark:text-slate-200">'
                . '<span class="h-2.5 w-2.5 shrink-0 rounded-full ' . $this->escape($segment['class']) . '"></span>'
                . '<span class="truncate">' . $this->escape($segment['label']) . '</span></span>'
                . '<span class="text-slate-500 dark:text-slate-400">' . $percent . '%</span></div>'
                . '<div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">'
                . '<span class="block h-full rounded-full ' . $this->escape($segment['class']) . '" style="width: ' . $percent . '%;"></span>'
                . '</div></div>';

            $cursor = $end;
        }

        $gradientCss = $gradient !== [] ? implode(', ', $gradient) : '#e2e8f0 0% 100%';

        return '<article class="rounded-lg border border-slate-200 bg-white p-5 shadow-lg shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">'
            . $this->renderHeader($total . ' total')
            . '<div class="mt-5 flex items-center gap-5">'
            . '<div class="grid h-28 w-28 shrink-0 place-items-center rounded-full" style="background: conic-gradient(' . $this->escape($gradientCss) . ');" aria-hidden="true">'
            . '<div class="grid h-16 w-16 place-items-center rounded-full bg-white text-sm font-extrabold text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white">' . $total . '</div>'
            . '</div><div class="grid min-w-0 flex-1 gap-3">' . $legend . '</div></div></article>';
    }
}
