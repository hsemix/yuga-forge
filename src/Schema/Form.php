<?php

namespace Yuga\Forge\Schema;

use Yuga\Forge\Fields\Field;

class Form
{
    /** @var array<Field|Section> */
    protected array $schema = [];

    public static function make(): static
    {
        return new static();
    }

    /**
     * @param array<Field|Section> $schema
     */
    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * The raw schema as declared - a mix of Fields and Sections, in display
     * order. Only Resource's own form renderer needs this; everything else
     * (validation, save(), etc.) wants getFields() instead.
     *
     * @return array<Field|Section>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Every Field in the form, flattened through any Sections - so a
     * consumer that just needs "all the fields" (validation, building the
     * save payload, the "Details" view's field fallback) doesn't need to
     * know Section exists at all.
     *
     * @return Field[]
     */
    public function getFields(): array
    {
        $fields = [];

        foreach ($this->schema as $item) {
            if ($item instanceof Section) {
                array_push($fields, ...$item->getFields());
            } else {
                $fields[] = $item;
            }
        }

        return $fields;
    }
}
