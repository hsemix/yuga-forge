<?php

namespace Yuga\Forge\Actions;

/**
 * A declarative row/bulk/header action button - replaces hand-built
 * <button ylc:click="..."> HTML strings that used to get copy-pasted (along
 * with their Tailwind classes) into every Resource subclass that wanted a
 * custom action. Stays Resource-agnostic on purpose (no reference to
 * Resource, same as Field/Filter/Column): it only stores an ability name via
 * can(), and it's the caller's job to combine isVisible() with the
 * Resource's own can() check before deciding to render at all.
 */
class Action
{
    protected string $name;
    protected ?string $label = null;
    protected string $color = 'default';
    protected ?string $icon = null;
    protected string|\Closure|null $confirm = null;
    protected ?\Closure $visibleCallback = null;
    protected ?string $ability = null;
    protected bool $plain = false;
    protected string $method = '';
    protected array $params = [];
    protected bool $usesRecordKey = true;
    protected string|\Closure|null $url = null;

    protected const ROW_COLOR_CLASSES = [
        'default' => 'h-10 rounded-lg border border-slate-200 bg-white px-3 font-bold text-slate-600 hover:border-azure-200 hover:bg-azure-50 hover:text-azure-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-azure-500/40 dark:hover:bg-azure-500/10 dark:hover:text-azure-300',
        'primary' => 'h-10 rounded-lg bg-azure-600 px-3 font-bold text-white shadow-sm hover:bg-azure-700',
        'danger' => 'h-10 rounded-lg border border-red-200 bg-white px-3 font-bold text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:bg-slate-900',
        'success' => 'h-10 rounded-lg border border-emerald-200 bg-white px-3 font-bold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/30 dark:bg-slate-900',
        'warning' => 'h-10 rounded-lg border border-amber-200 bg-white px-3 font-bold text-amber-700 hover:bg-amber-50 dark:border-amber-500/30 dark:bg-slate-900',
    ];

    protected const COMPACT_COLOR_CLASSES = [
        'default' => 'rounded-md border border-slate-200 bg-white px-2.5 py-1 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900',
        'primary' => 'rounded-md bg-azure-600 px-2.5 py-1 text-white hover:bg-azure-700',
        'danger' => 'rounded-md border border-red-200 bg-white px-2.5 py-1 text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:bg-slate-900',
        'success' => 'rounded-md border border-emerald-200 bg-white px-2.5 py-1 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/30 dark:bg-slate-900',
        'warning' => 'rounded-md border border-amber-200 bg-white px-2.5 py-1 text-amber-700 hover:bg-amber-50 dark:border-amber-500/30 dark:bg-slate-900',
    ];

    public static function make(string $name): static
    {
        $action = new static();
        $action->name = $name;

        return $action;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst(str_replace('_', ' ', $this->name));
    }

    /** @param 'default'|'primary'|'danger'|'success'|'warning' $color */
    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    /** Plain string only - no icon library exists anywhere in this codebase (see Widgets\StatsWidget). */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /** @param string|\Closure $message A literal message, or fn (int $count): string for bulk actions. */
    public function confirm(string|\Closure $message): static
    {
        $this->confirm = $message;

        return $this;
    }

    public function getConfirm(?int $count = null): ?string
    {
        if ($this->confirm instanceof \Closure) {
            return ($this->confirm)($count);
        }

        return $this->confirm;
    }

    /** @param \Closure $callback fn (array $record): bool */
    public function visible(\Closure $callback): static
    {
        $this->visibleCallback = $callback;

        return $this;
    }

    /** The inverse of visible() - shows the action when the callback returns false. */
    public function hidden(\Closure $callback): static
    {
        $this->visibleCallback = fn (array $record) => !$callback($record);

        return $this;
    }

    public function isVisible(array $record = []): bool
    {
        return $this->visibleCallback === null ? true : (bool) ($this->visibleCallback)($record);
    }

    /** The ability name a caller should check via $resource->can($ability, $record) before rendering. */
    public function can(string $ability): static
    {
        $this->ability = $ability;

        return $this;
    }

    public function getAbility(): ?string
    {
        return $this->ability;
    }

    /** Renders as a non-interactive <span> instead of a <button> - for states with nothing actionable left (e.g. an already-settled order). */
    public function plain(bool $plain = true): static
    {
        $this->plain = $plain;

        return $this;
    }

    public function isPlain(): bool
    {
        return $this->plain;
    }

    /** @param string $method A Resource method name, called via ylc:click. */
    public function action(string $method, array $params = []): static
    {
        $this->method = $method;
        $this->params = $params;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Renders as a plain <a href="..."> instead of a <button ylc:click="...">
     * - for actions that navigate/download rather than call a Resource
     * method (e.g. a report's download link).
     *
     * @param string|\Closure $url A literal URL, or fn (?string $recordKey, array $record): string.
     */
    public function url(string|\Closure $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(?string $recordKey = null, array $record = []): ?string
    {
        if ($this->url instanceof \Closure) {
            return ($this->url)($recordKey, $record);
        }

        return $this->url;
    }

    /** Header/bulk actions have no single record to key off of. */
    public function withoutRecordKey(): static
    {
        $this->usesRecordKey = false;

        return $this;
    }

    /**
     * @param string|null $recordKey The owning record's key, prepended as the
     *                                 first call argument unless withoutRecordKey() was set.
     * @param string $style 'row'|'compact' - which Tailwind class map to render with.
     */
    public function render(?string $recordKey, array $record = [], string $style = 'row', ?int $count = null): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $label = $escape($this->getLabel());
        $icon = $this->icon !== null ? $escape($this->icon) . ' ' : '';

        if ($this->plain) {
            return '<span class="text-slate-500 dark:text-slate-400">' . $icon . $label . '</span>';
        }

        $classes = $style === 'compact'
            ? (static::COMPACT_COLOR_CLASSES[$this->color] ?? static::COMPACT_COLOR_CLASSES['default'])
            : (static::ROW_COLOR_CLASSES[$this->color] ?? static::ROW_COLOR_CLASSES['default']);

        if ($this->url !== null) {
            $href = $escape((string) $this->getUrl($recordKey, $record));

            return '<a class="' . $classes . ' inline-flex items-center" href="' . $href . '">' . $icon . $label . '</a>';
        }

        $args = $this->usesRecordKey && $recordKey !== null
            ? [$recordKey, ...$this->params]
            : $this->params;

        $jsArgs = implode(', ', array_map(
            fn ($arg) => "'" . addslashes((string) $arg) . "'",
            $args
        ));

        $confirm = $this->getConfirm($count);
        $confirmAttr = $confirm !== null ? ' ys-confirm="' . $escape($confirm) . '"' : '';

        return '<button type="button" class="' . $classes . '"' . $confirmAttr
            . ' ylc:click="' . $escape($this->method . '(' . $jsArgs . ')') . '">'
            . $icon . $label . '</button>';
    }
}
