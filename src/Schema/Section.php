<?php

namespace Yuga\Forge\Schema;

use Yuga\Forge\Fields\Field;

/**
 * Groups a subset of a Form's fields - drop one or more of these directly
 * into Form::schema() alongside (or instead of) bare Field instances. The
 * heading is optional: a Section is also just a plain way to lay a few
 * fields out in a grid (see columns()) without necessarily labeling the
 * group at all. Form::getFields() flattens through these automatically, so
 * every existing consumer (validation, save(), the "Details" fallback
 * field list) keeps working unchanged; only the form's own renderer needs
 * to know about Section specifically (see Form::getSchema()).
 */
class Section
{
    protected ?string $heading = null;
    protected ?string $description = null;
    protected int $columns = 1;
    protected bool $collapsible = false;
    protected bool $collapsed = false;

    /** @var Field[] */
    protected array $fields = [];

    public static function make(?string $heading = null): static
    {
        $section = new static();
        $section->heading = $heading;

        return $section;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Lays the section's fields out in a grid instead of stacked single-file
     * (1 = the default). Supports 1-4; ask Resource::sectionGridClass() for
     * the literal class, not a computed one.
     */
    public function columns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /** @param Field[] $fields */
    public function schema(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Lets the section be toggled open/closed by clicking its heading - has
     * no effect on a Section with no heading() set, since there'd be
     * nothing to click. Purely client-side (see Resource::renderFormSection()),
     * same as Tabs.
     */
    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    /** Starts closed instead of open - only meaningful alongside collapsible(). */
    public function collapsed(bool $collapsed = true): static
    {
        $this->collapsed = $collapsed;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    /** @return Field[] */
    public function getFields(): array
    {
        return $this->fields;
    }
}
