---
layout: default
title: Fields
nav_order: 4
has_children: true
---

# Fields

Fields define what inputs appear in a resource's create/edit form. Every field type extends `Yuga\Forge\Fields\Field` and shares the same common API described here.

## Common API

### `make(string $name): static`

Static factory. `$name` must match the model column (or the key you want in `$this->data`).

```php
TextInput::make('email')
```

### `label(string $label): static`

Overrides the auto-generated label (defaults to `ucfirst(str_replace('_', ' ', $name))`).

### `required(): static`

Adds a `required` validation rule.

### `minLength(int $length): static` / `maxLength(int $length): static`

Add `min:{n}` / `max:{n}` validation rules.

### `rule(string $rule): static`

Add any raw validation rule string.

### `default(mixed $value): static`

Value pre-filled when the form opens for a new record.

### `visible(\Closure $callback): static`

Show this field conditionally based on the rest of the form state. The callback receives the full `$data` array:

```php
TextInput::make('referral_detail')
    ->visible(fn(array $data) => ($data['referral_source'] ?? null) === 'other'),
```

Every `ylc:model` change triggers a server round-trip, so the condition is re-evaluated on each keystroke — no JavaScript needed.

### `hidden(\Closure $callback): static`

Inverse of `visible()`.

### `dehydrateUsing(\Closure $callback): static`

Transform the value before it's written to the database:

```php
TextInput::make('slug')
    ->dehydrateUsing(fn($v) => Str::slug($v)),
```

### `hydrateUsing(\Closure $callback): static`

Transform the stored value when loading a record for editing.

## Storage conventions

Fields that hold multiple values (TagsInput, MultiSelect, Repeater) serialize to **JSON in a `TEXT` column** automatically via `dehydrate()`/`hydrate()`. No migration change is needed beyond a plain `$table->text('interests')`.

## Fields reference

| Class | Use for |
|-------|---------|
| [TextInput](text-input) | Single-line text, email, number, password |
| [Textarea](textarea) | Multi-line plain text |
| [RichEditor](rich-editor) | Markdown-flavoured textarea with toolbar |
| [Select](select) | Dropdown — static options or relationship combobox |
| [MultiSelect](multi-select) | Multiple-choice dropdown |
| [Radio](radio) | Visible radio button group |
| [Toggle](toggle) | Checkbox for boolean columns |
| [DatePicker](date-picker) | Date or datetime-local input |
| [TagsInput](tags-input) | Free-form chip list |
| [Repeater](repeater) | Dynamic list of field-group rows |
| [FileUpload](file-upload) | File / image upload with preview |
| [Hidden](hidden) | Hidden input, no visible label |
