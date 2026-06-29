<?php

namespace Yuga\Forge\Fields;

abstract class Field
{
    protected string $name;
    protected ?string $label = null;
    protected array $rules = [];
    protected mixed $default = null;
    protected ?\Closure $dehydrateCallback = null;
    protected ?\Closure $hydrateCallback = null;
    protected ?\Closure $visibleCallback = null;

    public static function make(string $name): static
    {
        $field = new static();
        $field->name = $name;

        return $field;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function required(): static
    {
        $this->rules[] = 'required';

        return $this;
    }

    public function minLength(int $length): static
    {
        $this->rules[] = "min:{$length}";

        return $this;
    }

    public function maxLength(int $length): static
    {
        $this->rules[] = "max:{$length}";

        return $this;
    }

    public function rule(string $rule): static
    {
        $this->rules[] = $rule;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst(str_replace('_', ' ', $this->name));
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Transforms a form-state value into what should be persisted (e.g. a
     * MultiSelect's array into a JSON string for a plain TEXT column).
     * Override per field-type, or attach one ad hoc via dehydrateUsing().
     */
    public function dehydrate(mixed $value): mixed
    {
        return $this->dehydrateCallback ? ($this->dehydrateCallback)($value) : $value;
    }

    /**
     * The inverse of dehydrate(): transforms a stored value back into form
     * state when a record is loaded for editing.
     */
    public function hydrate(mixed $value): mixed
    {
        return $this->hydrateCallback ? ($this->hydrateCallback)($value) : $value;
    }

    public function dehydrateUsing(\Closure $callback): static
    {
        $this->dehydrateCallback = $callback;

        return $this;
    }

    public function hydrateUsing(\Closure $callback): static
    {
        $this->hydrateCallback = $callback;

        return $this;
    }

    /**
     * Read-only rendering of a stored value, used by Resource's "Details"
     * view slide-over for any field that isn't already shown as a table
     * column. $value is the raw stored value (e.g. a JSON string for
     * MultiSelect), not hydrated form state - override per field-type for
     * anything that needs decoding/special display.
     */
    public function renderDisplay(mixed $value): string
    {
        if (is_array($value)) {
            return $value === [] ? '&mdash;' : htmlspecialchars(implode(', ', $value), ENT_QUOTES, 'UTF-8');
        }

        return $value === null || $value === '' ? '&mdash;' : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * True for a field that should never get a visible label/row anywhere -
     * not in the form (just the bare input), not in the "Details" view's
     * fallback field list. Only Hidden overrides this; everything else
     * stays visible by default.
     */
    public function isHiddenField(): bool
    {
        return false;
    }

    /**
     * Conditionally show this field based on the rest of the form's
     * current state, e.g. Radio::make('referral_source')->options([...]),
     * TextInput::make('referral_detail')->visible(fn ($data) =>
     * ($data['referral_source'] ?? null) === 'other'). The callback
     * receives the form's full $data array (not just this field's value).
     * Every input already triggers a full server round-trip + morph
     * (debounced ylc:model), so re-evaluating this on each render is all
     * that's needed - no client-side wiring required.
     */
    public function visible(\Closure $callback): static
    {
        $this->visibleCallback = $callback;

        return $this;
    }

    /** The inverse of visible() - shows the field when the callback returns false. */
    public function hidden(\Closure $callback): static
    {
        $this->visibleCallback = fn (array $data) => !$callback($data);

        return $this;
    }

    /**
     * Whether this field should render/validate/save given the form's
     * current $data. Defaults to true - only fields that called visible()
     * or hidden() are conditional at all.
     */
    public function isVisible(array $data): bool
    {
        return $this->visibleCallback === null ? true : (bool) ($this->visibleCallback)($data);
    }

    abstract public function renderInput(mixed $value, ?string $error): string;

    public function render(mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $label = $escape($this->getLabel());
        $input = $this->renderInput($value, $error);
        $errorHtml = $error
            ? '<small class="font-medium text-red-600">' . $escape($error) . '</small>'
            : '';

        return <<<HTML
            <label class="grid gap-1.5">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{$label}</span>
                {$input}
                {$errorHtml}
            </label>
            HTML;
    }

    /**
     * Deliberately no fixed height here - a single-line input/select needs
     * h-10, but a multi-line one (Textarea, RichEditor's textarea) needs to
     * size from its rows attribute instead; a fixed height on those would
     * silently override it down to one line regardless of rows. Single-line
     * field types add h-10 themselves (see TextInput/Select/DatePicker/
     * FileUpload); MultiSelect adds its own min-h instead.
     */
    protected static function inputClass(): string
    {
        return 'w-full rounded-lg border border-slate-200 bg-white px-3 text-slate-950 outline-none focus:border-azure-600 focus:ring-4 focus:ring-azure-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-azure-400 dark:focus:ring-azure-500/20';
    }

    protected function modelAttr(): string
    {
        return 'data.' . $this->getName();
    }
}
