<?php

namespace Yuga\Forge\Schema;

/**
 * Groups a form's fields into switchable tabs - drop directly into
 * Form::schema() alongside (or instead of) bare Fields/Sections, same as
 * Section. Tab switching is purely client-side (no server round-trip,
 * nothing to persist about which tab is open) - it's a presentational
 * concern only, the same reasoning that keeps Section un-collapsible today.
 *
 * Form::getFields() flattens through every Tab's fields automatically, so
 * validation/save()/the "Details" fallback list don't need to know Tabs
 * exists; only Resource's own form renderer does (via Form::getSchema()).
 */
class Tabs
{
    /** @var Tab[] */
    protected array $tabs = [];

    /** @param Tab[] $tabs */
    public static function make(array $tabs): static
    {
        $instance = new static();
        $instance->tabs = $tabs;

        return $instance;
    }

    /** @return Tab[] */
    public function getTabs(): array
    {
        return $this->tabs;
    }
}
