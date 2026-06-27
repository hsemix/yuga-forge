<?php

namespace Yuga\Forge\Columns;

abstract class Column
{
    protected string $name;
    protected ?string $label = null;
    protected bool $sortable = false;
    protected bool $searchable = false;
    protected array $extraSearchable = [];
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

    /**
     * @param string[] $extraColumns Other real DB columns to also match (e.g. an
     *                               "email" column shown only as this column's
     *                               description, but still worth searching).
     */
    public function searchable(array $extraColumns = []): static
    {
        $this->searchable = true;
        $this->extraSearchable = $extraColumns;

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
        // A dotted name (e.g. "customer.name") is a relation column. Whether
        // it's actually pushable to SQL depends on whether the resource can
        // join that relation (Resource::relationJoin() — to-one relations
        // only); that's resolved per-query in Resource::baseQuery(), not here.
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Columns to search for this column: itself plus any declared via
     * searchable([...]). May include dotted relation names (e.g.
     * "customer.name") — Resource::baseQuery() resolves whether each is
     * actually pushable to SQL (joinable relation) and drops it otherwise.
     *
     * @return string[]
     */
    public function getSearchableColumns(): array
    {
        if (!$this->searchable) {
            return [];
        }

        return array_values(array_unique([$this->name, ...$this->extraSearchable]));
    }

    protected function value(array $record): mixed
    {
        $value = $this->dig($record, $this->name);

        return $this->formatUsing ? ($this->formatUsing)($value, $record) : $value;
    }

    /**
     * Reads a (possibly dotted, e.g. "customer.name") path out of a record,
     * matching how eager-loaded relations nest under their name in toArray().
     */
    protected function dig(array $record, string $path): mixed
    {
        $value = $record;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
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
