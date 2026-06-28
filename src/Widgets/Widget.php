<?php

namespace Yuga\Forge\Widgets;

/**
 * Base class for a reusable dashboard widget: a self-contained piece of
 * markup (a stat card row, a chart, ...) any page can drop in, not just a
 * Resource. Deliberately a plain class, not a Yuga\Live\Component - a
 * widget's own interactivity (if any, e.g. a range selector) belongs to
 * whatever Live component embeds it; the widget itself just turns data into
 * HTML, the same relationship Fields/Columns/Filters already have to
 * Resource.
 */
abstract class Widget
{
    public static function make(): static
    {
        return new static();
    }

    abstract public function render(): string;

    /**
     * Overridable label shown above a widget's content. Null (the default)
     * renders no header at all - most widgets won't need to override this.
     */
    protected function eyebrow(): ?string
    {
        return null;
    }

    protected function heading(): ?string
    {
        return null;
    }

    protected function renderHeader(?string $trailing = null): string
    {
        $eyebrow = $this->eyebrow();
        $heading = $this->heading();

        if ($eyebrow === null && $heading === null && $trailing === null) {
            return '';
        }

        $label = '';

        if ($eyebrow !== null) {
            $label .= '<span class="text-xs font-extrabold uppercase text-azure-600">' . $this->escape($eyebrow) . '</span>';
        }

        if ($heading !== null) {
            $label .= '<h2 class="mt-1 text-lg font-bold leading-tight text-slate-950 dark:text-white">' . $this->escape($heading) . '</h2>';
        }

        $trailingHtml = $trailing !== null
            ? '<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">' . $this->escape($trailing) . '</span>'
            : '';

        return '<div class="flex items-start justify-between gap-4"><div>' . $label . '</div>' . $trailingHtml . '</div>';
    }

    protected function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
