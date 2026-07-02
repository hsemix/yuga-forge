---
title: Sections
sidebar_position: 1
---

# Sections

A `Section` groups a subset of a form's fields under an optional heading. Drop one directly into `Form::schema()` alongside bare fields:

```php
use Yuga\Forge\Schema\Section;
```

## Usage

```php
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->required(),

        Section::make('Additional details')
            ->description('Optional information about this customer.')
            ->columns(2)
            ->schema([
                Radio::make('referral_source')->options([...]),
                TextInput::make('referral_detail')
                    ->visible(fn($data) => ($data['referral_source'] ?? null) === 'other'),
                TagsInput::make('interests'),
            ]),
    ]);
}
```

## Collapsible sections

```php
Section::make('More about them')
    ->collapsible()
    ->collapsed()   // starts closed
    ->schema([...]),
```

Clicking the section heading toggles it open/closed — purely client-side via YugaJS's headless-disclosure component, no server round-trip.

## Methods

| Method | Purpose |
|--------|---------|
| `make(?string $heading = null): static` | Static factory. Heading is optional — a section without a heading is purely a layout grid. |
| `description(string $description): static` | Subtitle text below the heading |
| `columns(int $columns): static` | Lay fields out in a 1–4 column grid (default: 1) |
| `schema(array $fields): static` | The fields this section contains |
| `collapsible(bool $collapsible = true): static` | Make the section toggle-able by clicking its heading |
| `collapsed(bool $collapsed = true): static` | Start the section in the closed state (only meaningful with `collapsible()`) |

## Notes

- `Form::getFields()` flattens through `Section` automatically, so validation, save, and the "Details" view all see fields regardless of which section they're in.
- A section with no heading and `collapsible()` set has no visible toggle — `collapsible()` has no effect without a heading.
