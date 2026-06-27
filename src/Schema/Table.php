<?php

namespace Yuga\Forge\Schema;

use Yuga\Forge\Columns\Column;
use Yuga\Forge\Filters\Filter;

class Table
{
    /** @var Column[] */
    protected array $columns = [];

    /** @var Filter[] */
    protected array $filters = [];

    public static function make(): static
    {
        return new static();
    }

    /**
     * @param Column[] $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @param Filter[] $filters
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return Filter[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
