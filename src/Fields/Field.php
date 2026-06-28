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

    protected static function inputClass(): string
    {
        return 'h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-slate-950 outline-none focus:border-azure-600 focus:ring-4 focus:ring-azure-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-azure-400 dark:focus:ring-azure-500/20';
    }

    protected function modelAttr(): string
    {
        return 'data.' . $this->getName();
    }
}
