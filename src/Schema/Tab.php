<?php

namespace Yuga\Forge\Schema;

use Yuga\Forge\Fields\Field;

/**
 * One tab inside a Tabs component - just a label + its own field list.
 * Only meaningful nested inside Tabs::make([...]); doesn't do anything
 * placed directly into Form::schema() on its own.
 */
class Tab
{
    protected string $label;

    /** @var Field[] */
    protected array $fields = [];

    public static function make(string $label): static
    {
        $tab = new static();
        $tab->label = $label;

        return $tab;
    }

    /** @param Field[] $fields */
    public function schema(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /** @return Field[] */
    public function getFields(): array
    {
        return $this->fields;
    }
}
