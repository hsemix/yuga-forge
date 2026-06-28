<?php

namespace Yuga\Forge\Columns;

/**
 * Renders a thumbnail for a stored file path (the same shape a FileUpload
 * field commits - see Yuga\Forge\Fields\FileUpload::commit()) - a plain
 * string, or null/empty for "no image". A fresh, uncommitted upload's
 * {token,name,...} array shape never reaches here: that only exists
 * transiently in form state, never in a persisted record this column reads.
 */
class ImageColumn extends Column
{
    protected string $size = 'md';
    protected bool $circular = false;

    /**
     * @param 'sm'|'md'|'lg' $size
     */
    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function circular(): static
    {
        $this->circular = true;

        return $this;
    }

    public function renderCell(array $record): string
    {
        $value = $this->value($record);
        $url = is_string($value) && $value !== '' ? $value : null;
        $shapeClass = $this->circular ? 'rounded-full' : 'rounded-md';

        if ($url === null) {
            return '<span class="inline-flex ' . $this->sizeClass() . ' items-center justify-center ' . $shapeClass . ' bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">&mdash;</span>';
        }

        return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" class="' . $this->sizeClass() . ' ' . $shapeClass . ' object-cover">';
    }

    /**
     * A fixed set of literal classes, not a dynamically built "h-{$n}
     * w-{$n}" string - Tailwind only generates CSS for classes it finds as
     * literal text while scanning source files, so a runtime-built class
     * would silently produce no sizing at all (hit this twice already this
     * session with BarChartWidget's bar heights and the rich-text toolbar).
     */
    protected function sizeClass(): string
    {
        return match ($this->size) {
            'sm' => 'h-8 w-8',
            'lg' => 'h-16 w-16',
            default => 'h-10 w-10',
        };
    }
}
