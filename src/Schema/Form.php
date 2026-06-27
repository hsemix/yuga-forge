<?php

namespace Yuga\Forge\Schema;

use Yuga\Forge\Fields\Field;

class Form
{
    /** @var Field[] */
    protected array $fields = [];

    public static function make(): static
    {
        return new static();
    }

    /**
     * @param Field[] $fields
     */
    public function schema(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
