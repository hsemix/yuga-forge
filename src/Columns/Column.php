<?php

namespace Yuga\Forge\Columns;

abstract class Column
{
    protected string $name;
    protected ?string $label = null;
    protected bool $sortable = false;
    protected bool $searchable = false;
    protected ?\Closure $formatUsing = null;

    public static function make(string $name): static
    {
        $column = new static();
        $column->name = $name;

        return $column;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function sortable(): static
    {
        $this->sortable = true;

        return $this;
    }

    public function searchable(): static
    {
        $this->searchable = true;

        return $this;
    }

    public function formatUsing(\Closure $callback): static
    {
        $this->formatUsing = $callback;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst(str_replace('_', ' ', $this->name));
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    protected function value(array $record): mixed
    {
        $value = $record[$this->name] ?? null;

        return $this->formatUsing ? ($this->formatUsing)($value, $record) : $value;
    }

    abstract public function renderCell(array $record): string;

    public function renderHeader(string $currentSort, string $currentDirection): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $label = $escape($this->getLabel());

        if (!$this->sortable) {
            return $label;
        }

        $mark = $currentSort === $this->name
            ? ($currentDirection === 'asc' ? '&uarr;' : '&darr;')
            : '';

        return '<button class="flex items-center gap-1.5 uppercase" type="button" ylc:click="sortBy(\'' . $this->name . '\')">'
            . $label . ' <span class="text-azure-600">' . $mark . '</span></button>';
    }
}
