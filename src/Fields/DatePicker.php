<?php

namespace Yuga\Forge\Fields;

class DatePicker extends Field
{
    protected bool $withTime = false;

    public function withTime(): static
    {
        $this->withTime = true;

        return $this;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $type = $this->withTime ? 'datetime-local' : 'date';

        return '<input type="' . $type . '" class="h-10 ' . static::inputClass() . '" value="' . $escape($value) . '" ylc:model="' . $this->modelAttr() . '">';
    }

    /**
     * An emptied date input sends '' (a native <input type="date"> can't
     * send null directly), but a DATE/DATETIME column rejects '' outright
     * ("Incorrect date value: ''") - it needs a real NULL for "no date".
     */
    public function dehydrate(mixed $value): mixed
    {
        return parent::dehydrate($value === '' ? null : $value);
    }
}
