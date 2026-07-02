---
title: TagsInput
sidebar_position: 9
---

# TagsInput

A free-form chip input: the user types a value and presses **Enter** or **,** to add it as a chip. Clicking a chip's **×** removes it. No predefined option list.

```php
use Yuga\Forge\Fields\TagsInput;
```

## Usage

```php
TagsInput::make('interests')
    ->label('Interests')
    ->placeholder('Type an interest, press Enter...'),
```

## Methods

| Method | Purpose |
|--------|---------|
| `placeholder(string $placeholder): static` | Placeholder text in the text input (default: `'Add a tag...'`) |

All [common field methods](index#common-api) also apply.

## Storage

Tags are serialized as a JSON array in a `TEXT` column:

```json
["php", "databases", "open-source"]
```

`dehydrate()` and `hydrate()` handle the serialization/deserialization automatically. Migration:

```php
$table->text('interests')->nullable();
```

## Differences from MultiSelect

| | TagsInput | MultiSelect |
|-|-----------|-------------|
| Options | Free-form, user types anything | Fixed list declared in PHP |
| Storage | JSON array in TEXT | JSON array in TEXT |
| UI | Chips with text input | Native `<select multiple>` |

## Notes

Chip add/remove is implemented with inline `onkeydown`/`onclick` attribute handlers — no `<script>` tag injection. This matters because YLC's DOM morphing (via morphdom) does not re-execute injected `<script>` tags, but attribute-based handlers still fire correctly.
