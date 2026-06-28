<?php

namespace Yuga\Forge\Relations;

/**
 * Declares a related model's records to show inline in a Resource's
 * "Details" view slide-over (e.g. a Customer's Orders) - Filament calls
 * this a relation manager. Reuses Yuga\Forge\Columns\Column for rendering
 * each related record's cells, the same as a Resource's own table().
 */
class RelationManager
{
    protected string $relation;
    protected ?string $label = null;

    /** @var \Yuga\Forge\Columns\Column[] */
    protected array $columns = [];

    protected ?int $limit = null;

    /**
     * @param string $relation The name of a to-many relation method on the
     *                          parent Resource's model (e.g. "orders" for
     *                          Model::orders(): HasMany).
     */
    public static function make(string $relation): static
    {
        $manager = new static();
        $manager->relation = $relation;

        return $manager;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** @param \Yuga\Forge\Columns\Column[] $columns */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst(str_replace('_', ' ', $this->relation));
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
