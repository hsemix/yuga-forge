<?php

namespace Yuga\Forge\Fields;

/**
 * A dynamic list of repeated field groups (e.g. order line items, product
 * variants) - each row is built from the same sub-schema and stored as a
 * JSON-encoded array of associative arrays in a plain TEXT column, the same
 * convention TagsInput/MultiSelect already use.
 *
 * Unlike TagsInput's client-side chip add/remove, adding/removing a row
 * changes the shape of the form itself (a whole new block of inputs
 * appears/disappears), so it's a real server round-trip rather than inline
 * JS - Resource::addRepeaterRow()/removeRepeaterRow() splice
 * $this->data[$name] directly, then the next render reflects the new row
 * count. Each row's sub-fields bind via Field::withModelPrefix() to
 * "data.{name}.{index}", which Resource's existing array-bucket dot-path
 * handling already walks without any further change.
 */
class Repeater extends Field
{
    /** @var Field[] */
    protected array $fields = [];

    /** @param Field[] $fields */
    public function schema(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /** @return Field[] */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function renderInput(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rows = $this->normalize($value);

        $html = '<div class="grid gap-3">';

        foreach ($rows as $index => $row) {
            $html .= '<div class="grid gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800">';
            $html .= '<div class="flex justify-end"><button type="button" class="text-xs font-bold text-red-500 hover:text-red-600" ylc:click="removeRepeaterRow(\'' . $escape($this->getName()) . '\', ' . (int) $index . ')">Remove</button></div>';
            $html .= '<div class="grid gap-3 sm:grid-cols-2">';

            foreach ($this->fields as $field) {
                $subField = (clone $field)->withModelPrefix('data.' . $this->getName() . '.' . $index);
                $subValue = is_array($row) ? ($row[$field->getName()] ?? $field->getDefault()) : $field->getDefault();
                $html .= $subField->render($subValue, null);
            }

            $html .= '</div></div>';
        }

        $html .= '<button type="button" class="h-9 rounded-lg border border-dashed border-slate-300 text-sm font-bold text-slate-500 hover:border-azure-300 hover:text-azure-600 dark:border-slate-700 dark:text-slate-400" ylc:click="addRepeaterRow(\'' . $escape($this->getName()) . '\')">+ Add row</button>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Field::render() wraps every field in an outer <label> - correct for a
     * single control, but Repeater's own renderInput() output contains its
     * own per-row <label>s (one per sub-field), so the base wrapper would
     * produce invalid nested <label> markup. Same shape/classes, just a
     * <div> instead of a <label> at the top level.
     */
    public function render(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $label = $escape($this->getLabel());
        $input = $this->renderInput($value, $error);
        $errorHtml = $error
            ? '<small class="font-medium text-red-600">' . $escape($error) . '</small>'
            : '';

        return <<<HTML
            <div class="grid gap-1.5">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{$label}</span>
                {$input}
                {$errorHtml}
            </div>
            HTML;
    }

    public function dehydrate(mixed $value): mixed
    {
        return parent::dehydrate(json_encode($this->normalize($value)));
    }

    public function hydrate(mixed $value): mixed
    {
        return parent::hydrate($this->normalize($value));
    }

    public function renderDisplay(mixed $value): string
    {
        $rows = $this->normalize($value);

        if ($rows === []) {
            return '&mdash;';
        }

        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $lines = [];

        foreach ($rows as $row) {
            $parts = [];

            foreach ($this->fields as $field) {
                $parts[] = $field->getLabel() . ': ' . $escape($row[$field->getName()] ?? '');
            }

            $lines[] = implode(', ', $parts);
        }

        return implode('<br>', $lines);
    }

    /**
     * $value may already be a real array (set during this session) or a
     * JSON-encoded string (freshly hydrated from storage) - accept either,
     * same as TagsInput::normalize().
     */
    protected function normalize(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
