<?php

namespace Yuga\Forge\Filters;

abstract class Filter
{
    protected string $name;
    protected ?string $label = null;
    protected mixed $default = 'all';

    public static function make(string $name): static
    {
        $filter = new static();
        $filter->name = $name;

        return $filter;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

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

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Whether this filter should actually narrow the record set for the given
     * current value. Default: "anything other than the declared default".
     * Override for filter types where that's not the right test.
     */
    public function shouldApply(mixed $value): bool
    {
        return $value !== $this->default;
    }

    abstract public function renderInput(mixed $value): string;

    abstract public function matches(array $record, mixed $filterValue): bool;
}
