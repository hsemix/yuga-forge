<?php

namespace Yuga\Forge;

use Yuga\Database\Elegant\Association\BelongsTo;
use Yuga\Database\Elegant\Association\HasOne;
use Yuga\Database\Elegant\Model;
use Yuga\Forge\Authorization\Policy;
use Yuga\Forge\Schema\Form;
use Yuga\Forge\Schema\Table;
use Yuga\Live\Attributes\Url;
use Yuga\Live\Component;
use Yuga\Models\Auth;

abstract class Resource extends Component
{
    /** @var class-string<\Yuga\Database\Elegant\Model> */
    protected string $model;

    protected string $recordKey = 'public_id';
    protected string $keyPrefix = 'REC';

    /** @var string[] relations to eager-load (passed straight to the model's ->with()) */
    protected array $with = [];

    /**
     * @var class-string<Policy>|null Explicit override for the policy class.
     *      Leave null to auto-resolve App\Models\X -> App\Policies\XPolicy
     *      (see resolvePolicyClass()); if neither is set/exists, every
     *      ability is allowed (no policy = open, not denied).
     */
    protected ?string $policy = null;

    protected ?Policy $resolvedPolicy = null;
    protected bool $policyResolved = false;

    /** @var string[] public array properties that accept dotted ylc:model bindings, e.g. "data.name" */
    protected array $arrayBuckets = ['data', 'filters'];

    #[Url(as: 'search', history: false)]
    public string $search = '';

    #[Url(as: 'sort', history: false)]
    public string $sort = '';

    #[Url(as: 'direction', history: false)]
    public string $direction = 'asc';

    #[Url(as: 'page', history: false)]
    public int $page = 1;

    #[Url(as: 'perPage', history: false)]
    public int $perPage = 10;

    public array $selected = [];
    public bool $showFilters = false;
    public array $filters = [];

    public bool $showForm = false;
    public ?string $editingKey = null;
    public array $data = [];

    public bool $showView = false;
    public ?array $viewing = null;

    abstract public static function form(Form $form): Form;

    abstract public static function table(Table $table): Table;

    public function mount(...$params): void
    {
        $table = static::table(Table::make());

        if ($this->sort === '') {
            $this->sort = $this->defaultSort() ?? $this->firstSortableColumn($table) ?? '';
            $this->direction = $this->defaultDirection();
        }

        if (empty($this->filters)) {
            foreach ($table->getFilters() as $filter) {
                $this->filters[$filter->getName()] = $filter->getDefault();
            }
        }
    }

    /**
     * "sort" doesn't have a static class-default — it's computed in mount()
     * (defaultSort(), or the first sortable column) — so the generic #[Url]
     * default-suppression in ylc-live-plugin.js (which reads the property's
     * *declared* default) would otherwise always show "?sort=..." even when
     * it's the effective default. Substitute the real computed default here
     * so the URL stays clean.
     */
    public function getUrlProperties(): array
    {
        $properties = parent::getUrlProperties();

        if (isset($properties['sort']) && $properties['sort']['default'] === '') {
            $properties['sort']['default'] = $this->defaultSort()
                ?? $this->firstSortableColumn(static::table(Table::make()))
                ?? '';
        }

        return $properties;
    }

    /**
     * Override to force a specific default sort column instead of "the first
     * sortable column" (e.g. a derived/log-like resource that should default
     * to newest-first instead of whatever column happens to be listed first).
     */
    protected function defaultSort(): ?string
    {
        return null;
    }

    protected function defaultDirection(): string
    {
        return 'asc';
    }

    protected function firstSortableColumn(Table $table): ?string
    {
        $instance = new ($this->model)();

        foreach ($table->getColumns() as $column) {
            if (!$column->isSortable()) {
                continue;
            }

            if (!str_contains($column->getName(), '.')) {
                return $column->getName();
            }

            [$relation] = explode('.', $column->getName(), 2);

            if ($this->relationJoin($instance, $relation) !== null) {
                return $column->getName();
            }
        }

        return null;
    }

    public function setPublicState(array $state): void
    {
        foreach ($state as $key => $value) {
            if (!str_contains($key, '.')) {
                continue;
            }

            $segments = explode('.', $key);
            $bucket = $segments[0];

            if (!in_array($bucket, $this->arrayBuckets, true)) {
                continue;
            }

            $path = array_slice($segments, 1);
            $current = $this->arrayGet($this->{$bucket}, $path);

            $this->arraySet($this->{$bucket}, $path, $value);

            if ($current !== $value) {
                $this->touch();
            }

            $this->updated($key, $value);
            unset($state[$key]);
        }

        parent::setPublicState($state);
    }

    protected function arrayGet(array $array, array $path): mixed
    {
        foreach ($path as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return null;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    protected function arraySet(array &$array, array $path, mixed $value): void
    {
        $segment = array_shift($path);

        if ($path === []) {
            $array[$segment] = $value;

            return;
        }

        if (!isset($array[$segment]) || !is_array($array[$segment])) {
            $array[$segment] = [];
        }

        $this->arraySet($array[$segment], $path, $value);
    }

    public function updated(string $property, mixed $value): void
    {
        if ($property === 'search' || $property === 'perPage' || str_starts_with($property, 'filters.')) {
            $this->page = 1;
        }
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->page = 1;
    }

    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    public function clearFilters(): void
    {
        $this->search = '';

        foreach (static::table(Table::make())->getFilters() as $filter) {
            $this->filters[$filter->getName()] = $filter->getDefault();
        }

        $this->page = 1;
    }

    public function toggleSelect(string $key): void
    {
        if (in_array($key, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$key]));
        } else {
            $this->selected[] = $key;
        }
    }

    public function selectPage(array $keys): void
    {
        $this->selected = array_values(array_unique(array_merge($this->selected, $keys)));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function setPerPage(int $perPage): void
    {
        $this->perPage = $perPage;
        $this->page = 1;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function openCreate(): void
    {
        if (!$this->can('create')) {
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(string $key): void
    {
        $record = $this->findRecord($key);

        if (!$record || !$this->can('update', $record)) {
            return;
        }

        $this->editingKey = $key;
        $this->data = [];

        foreach (static::form(Form::make())->getFields() as $field) {
            $this->data[$field->getName()] = array_key_exists($field->getName(), $record)
                ? $field->hydrate($record[$field->getName()])
                : $field->getDefault();
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->editingKey = null;
        $this->data = [];

        foreach (static::form(Form::make())->getFields() as $field) {
            $this->data[$field->getName()] = $field->getDefault();
        }
    }

    public function save(): void
    {
        if ($this->editingKey) {
            $existing = $this->findRecord($this->editingKey);

            if (!$existing || !$this->can('update', $existing)) {
                $this->closeForm();

                return;
            }
        } elseif (!$this->can('create')) {
            $this->closeForm();

            return;
        }

        $fields = static::form(Form::make())->getFields();

        foreach ($fields as $field) {
            $value = $this->data[$field->getName()] ?? null;

            foreach ($field->getRules() as $rule) {
                $this->validateRule($field->getName(), $value, $rule);
            }
        }

        if ($this->hasErrors()) {
            return;
        }

        $payload = $this->data;

        foreach ($fields as $field) {
            if (array_key_exists($field->getName(), $payload)) {
                $payload[$field->getName()] = $field->dehydrate($payload[$field->getName()]);
            }
        }

        if ($this->editingKey) {
            $payload['updated_at'] = $this->now();

            ($this->model)::where($this->recordKey, $this->editingKey)->update($payload);

            $this->toast('Record updated.');
            $this->afterSave(false, $this->editingKey);
        } else {
            $key = $this->generateKey();
            $payload[$this->recordKey] = $key;
            $payload['created_at'] = $this->now();

            ($this->model)::create($payload);

            $this->toast('Record created.');
            $this->afterSave(true, $key);
        }

        $this->closeForm();
    }

    /**
     * Hook for subclasses, e.g. to emit a notification after a record is created/updated.
     */
    protected function afterSave(bool $created, string $key): void
    {
    }

    public function openView(string $key): void
    {
        $record = $this->findRecord($key);

        if (!$record || !$this->can('view', $record)) {
            return;
        }

        $this->viewing = $record;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewing = null;
    }

    public function deleteOne(string $key): void
    {
        $record = $this->findRecord($key);

        if (!$record || !$this->can('delete', $record)) {
            return;
        }

        // delete(true) = permanent. Elegant's default delete() is a *soft*
        // delete, and if the table has no deleted_at column it will silently
        // ALTER TABLE to add one rather than removing the row — never what a
        // "Delete" confirm button here means.
        ($this->model)::where($this->recordKey, $key)->delete(true);
        $this->selected = array_values(array_diff($this->selected, [$key]));
        $this->toast('Record deleted.');
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $deleted = 0;

        foreach ($this->selected as $key) {
            $record = $this->findRecord($key);

            if (!$record || !$this->can('delete', $record)) {
                continue;
            }

            ($this->model)::where($this->recordKey, $key)->delete(true);
            $deleted++;
        }

        $this->toast($deleted . ' record(s) deleted.');
        $this->selected = [];
    }

    /**
     * Builds the base query for this resource: ordered, search-filtered,
     * filter-constrained, relations eager-loaded — everything except the
     * limit/offset, which paginate() applies. Search is pushed down as a single
     * grouped OR across searchable columns via where(Closure) — Elegant forwards
     * a one-argument where(Closure) straight to the underlying query builder,
     * which evaluates it against a NestedCriteria and wraps it in real
     * parentheses (confirmed via toSql()) — plain chained where()/orWhere()
     * calls have no such grouping and would mis-parse against any AND filter
     * added afterwards (SQL's AND binds tighter than OR).
     *
     * Sort/search columns may be dotted relation paths (e.g. "customer.name").
     * Those get qualified to the real joined table via relationJoin() — any
     * LEFT JOINs that turns up get added once, and the select() is pinned to
     * "$table.*" so the joined columns can constrain WHERE/ORDER BY without
     * polluting the hydrated model's own attributes (display still goes
     * through ->with(), which fetches relation data with separate queries).
     *
     * @return \Yuga\Database\Elegant\Builder
     */
    protected function baseQuery(Table $table)
    {
        $modelClass = $this->model;
        $instance = new $modelClass();
        $joins = [];

        $qualify = function (string $name) use ($instance, &$joins) {
            if (!str_contains($name, '.')) {
                return $instance->getTable() . '.' . $name;
            }

            [$relation, $column] = explode('.', $name, 2);

            if (!array_key_exists($relation, $joins)) {
                $joins[$relation] = $this->relationJoin($instance, $relation);
            }

            return $joins[$relation] !== null ? $joins[$relation]['table'] . '.' . $column : null;
        };

        $sortColumn = $this->sort !== ''
            ? ($qualify($this->sort) ?? $instance->getTable() . '.' . $instance->getPrimaryKey())
            : $instance->getTable() . '.' . $instance->getPrimaryKey();

        $query = $modelClass::orderBy($sortColumn, $this->direction === 'asc' ? 'asc' : 'desc');

        $search = trim($this->search);

        if ($search !== '' && $table->getColumns() !== []) {
            $searchable = array_values(array_unique(array_merge(
                ...array_map(fn ($column) => $column->getSearchableColumns(), $table->getColumns())
            )));

            $searchable = array_values(array_filter(array_map($qualify, $searchable)));

            if ($searchable !== []) {
                $like = '%' . $search . '%';

                $query->where(function ($nested) use ($searchable, $like) {
                    foreach ($searchable as $i => $column) {
                        $i === 0 ? $nested->where($column, 'like', $like) : $nested->orWhere($column, 'like', $like);
                    }
                });
            }
        }

        foreach ($table->getFilters() as $filter) {
            $value = $this->filters[$filter->getName()] ?? $filter->getDefault();

            if ($filter->shouldApply($value)) {
                $filter->apply($query, $value);
            }
        }

        $activeJoins = array_filter($joins);

        if ($activeJoins !== []) {
            $query->select($instance->getTable() . '.*');

            foreach ($activeJoins as $join) {
                $query->leftJoin($join['table'], $join['on'][0], '=', $join['on'][1]);
            }
        }

        if ($this->with !== []) {
            $query->with($this->with);
        }

        return $query;
    }

    /**
     * Resolves a relation method on the model into JOIN metadata: the related
     * table plus an ON condition, so Resource::baseQuery() can sort/search/
     * filter across it. Yuga's Association classes (BelongsTo/HasOne/etc.)
     * don't expose public getters for their foreignKey/otherKey — this reads
     * the protected properties directly via reflection rather than asking
     * consumers to redeclare FK details Forge could otherwise read straight
     * off the real relation definition.
     *
     * Only to-one relations are joined: BelongsTo and HasOne. A to-many
     * relation (HasMany/BelongsToMany) joined into a list of the *parent*
     * model would duplicate rows (one per related record) — out of scope
     * here, not a join Forge will ever attempt. Returns null for those, for a
     * relation method that doesn't exist, or if the model has no such method;
     * callers then treat anything addressing that relation as not pushable to
     * SQL (it can still be eager-loaded via $with and shown read-only).
     *
     * @return array{table: string, on: array{0: string, 1: string}}|null
     */
    protected function relationJoin(Model $instance, string $relation): ?array
    {
        if (!method_exists($instance, $relation)) {
            return null;
        }

        $association = $instance->$relation();

        if (!$association instanceof BelongsTo && !$association instanceof HasOne) {
            return null;
        }

        $reflection = new \ReflectionObject($association);
        $read = fn (string $property) => (function () use ($reflection, $association, $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);

            return $prop->getValue($association);
        })();

        $foreignKey = $read('foreignKey');
        $otherKey = $read('otherKey');
        $related = $read('child');

        if ($association instanceof BelongsTo) {
            // BelongsTo's foreignKey/otherKey are both unprefixed column names
            // (e.g. "customer_id" on the base table, "id" on the related one).
            return [
                'table' => $related->getTable(),
                'on' => [$instance->getTable() . '.' . $foreignKey, $related->getTable() . '.' . $otherKey],
            ];
        }

        // HasOne's foreignKey already includes the related table's prefix
        // (set up that way in Model::hasOne()); otherKey is the base table's
        // own (unprefixed) local key.
        return [
            'table' => $related->getTable(),
            'on' => [$foreignKey, $instance->getTable() . '.' . $otherKey],
        ];
    }

    protected function findRecord(string $key): ?array
    {
        $modelClass = $this->model;
        $query = $modelClass::where($this->recordKey, $key);

        if ($this->with !== []) {
            $query->with($this->with);
        }

        $record = $query->first();

        return $record?->toArray();
    }

    protected function pageLinks(int $page, int $pages): array
    {
        $window = 2;
        $start = max(1, $page - $window);
        $end = min($pages, $page + $window);

        if ($page <= $window + 1) {
            $end = min($pages, 1 + $window * 2);
        }

        if ($page >= $pages - $window) {
            $start = max(1, $pages - $window * 2);
        }

        return range($start, $end);
    }

    protected function generateKey(): string
    {
        $modelClass = $this->model;
        $max = 0;

        foreach ($modelClass::all([$this->recordKey]) as $record) {
            $number = (int) preg_replace('/\D+/', '', (string) ($record->{$this->recordKey} ?? ''));
            $max = max($max, $number);
        }

        return $this->keyPrefix . '-' . ($max + 1);
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Derives a display label from the class name, e.g. "CustomersResource" -> "Customers".
     * Override for custom copy.
     */
    protected function label(): string
    {
        $name = (new \ReflectionClass($this))->getShortName();
        $name = preg_replace('/Resource$/', '', $name);

        return trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', (string) $name));
    }

    public function render()
    {
        if (!$this->can('viewAny')) {
            return $this->renderForbidden();
        }

        $table = static::table(Table::make());
        $perPage = in_array($this->perPage, [5, 10, 25, 50], true) ? $this->perPage : 10;
        $requestedPage = max(1, $this->page);

        $query = $this->baseQuery($table);
        $results = $query->paginate($perPage, $requestedPage);
        $pagination = $query->getPagination();
        $pages = max(1, (int) $pagination->totalPages());

        // paginate()/Pagination don't clamp an out-of-range page themselves —
        // a stale page (e.g. from the URL, after a filter shrank the result
        // set) would otherwise just return zero rows instead of snapping back.
        if ($requestedPage > $pages) {
            $query = $this->baseQuery($table);
            $results = $query->paginate($perPage, $pages);
            $pagination = $query->getPagination();
        }

        $this->page = $pagination->getCurrentPage();

        $rows = [];

        foreach ($results as $record) {
            $rows[] = $record->toArray();
        }

        $total = $pagination->getTotalCount();
        $offset = ($this->page - 1) * $perPage;

        return $this->renderPage($table, [
            'rows' => $rows,
            'total' => $total,
            'page' => $this->page,
            'pages' => $pages,
            'perPage' => $perPage,
            'from' => $total ? $offset + 1 : 0,
            'to' => min($total, $offset + count($rows)),
            'pageLinks' => $this->pageLinks($this->page, $pages),
        ]);
    }

    protected function renderForbidden(): string
    {
        return '<div class="grid place-items-center rounded-lg border border-slate-200 bg-white p-12 text-center text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">You don\'t have permission to view this.</div>';
    }

    protected function renderPage(Table $table, array $pagination): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $label = $this->label();
        $buttonClass = 'h-10 rounded-lg border border-slate-200 bg-white px-3 font-bold text-slate-600 hover:border-azure-200 hover:bg-azure-50 hover:text-azure-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-azure-500/40 dark:hover:bg-azure-500/10 dark:hover:text-azure-300';
        $inputClass = 'h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-slate-950 outline-none focus:border-azure-600 focus:ring-4 focus:ring-azure-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-azure-400 dark:focus:ring-azure-500/20';

        $columns = $table->getColumns();
        $filters = $table->getFilters();
        $rows = $pagination['rows'];
        $pageKeys = array_column($rows, $this->recordKey);
        $jsKeys = fn (array $keys) => '[' . implode(',', array_map(fn ($key) => "'" . addslashes((string) $key) . "'", $keys)) . ']';
        $allSelected = $pageKeys !== [] && count(array_intersect($pageKeys, $this->selected)) === count($pageKeys);

        $html = '<div class="grid gap-5">';

        $html .= '<header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">';
        $html .= '<div><span class="text-xs font-extrabold uppercase text-azure-600">' . $escape($label) . '</span>';
        $html .= '<h1 class="mt-1 text-3xl font-bold leading-tight text-slate-950 dark:text-white">' . $escape($label) . '</h1></div>';

        if ($this->isCreatable()) {
            $html .= '<button type="button" class="h-10 rounded-lg bg-azure-600 px-4 font-bold text-white shadow-sm hover:bg-azure-700" ylc:click="openCreate">+ New</button>';
        }

        $html .= '</header>';

        $html .= '<section class="rounded-lg border border-slate-200 bg-white shadow-lg shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">';
        $html .= '<div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">';
        $html .= '<div class="flex flex-1 flex-col gap-2.5 sm:flex-row sm:items-center">';
        $html .= '<input class="' . $inputClass . ' sm:w-72" type="search" placeholder="Search" value="' . $escape($this->search) . '" ylc:model="search">';

        if ($filters !== []) {
            $html .= '<button type="button" class="' . $buttonClass . '" ylc:click="toggleFilters">' . ($this->showFilters ? 'Hide filters' : 'Filters') . '</button>';
        }

        $html .= '</div>';

        if ($this->selected !== []) {
            $count = count($this->selected);
            $html .= '<div class="flex items-center gap-2 rounded-lg bg-azure-50 px-3 py-2 text-sm font-bold text-azure-700 dark:bg-azure-500/10 dark:text-azure-200">';
            $html .= '<span>' . $count . ' selected</span>';
            $html .= $this->bulkActions($count);
            $html .= '<button type="button" class="text-azure-600 hover:underline" ylc:click="clearSelection">Clear</button>';
            $html .= '</div>';
        }

        $html .= '</div>';

        if ($this->showFilters && $filters !== []) {
            $html .= '<div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950 sm:flex-row sm:items-center">';

            foreach ($filters as $filter) {
                $value = $this->filters[$filter->getName()] ?? $filter->getDefault();
                $html .= '<label class="grid gap-1 text-sm"><span class="font-bold text-slate-600 dark:text-slate-300">' . $escape($filter->getLabel()) . '</span>' . $filter->renderInput($value) . '</label>';
            }

            $html .= '<button type="button" class="' . $buttonClass . ' mt-auto w-fit" ylc:click="clearFilters">Clear filters</button>';
            $html .= '</div>';
        }

        $html .= '<div class="overflow-x-auto"><table class="w-full min-w-[640px]"><thead><tr class="bg-slate-50 dark:bg-slate-900">';
        $html .= '<th class="w-10 border-t border-slate-200 px-5 py-3 dark:border-slate-800"><input type="checkbox" class="h-4 w-4 rounded border-slate-300"' . ($allSelected ? ' checked' : '') . ' ylc:click="' . ($allSelected ? 'clearSelection' : 'selectPage(' . $jsKeys($pageKeys) . ')') . '"></th>';

        foreach ($columns as $column) {
            $html .= '<th class="border-t border-slate-200 px-5 py-3 text-left text-xs font-bold uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">' . $column->renderHeader($this->sort, $this->direction) . '</th>';
        }

        $html .= '<th class="border-t border-slate-200 px-5 py-3 text-right text-xs font-bold uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">Actions</th>';
        $html .= '</tr></thead><tbody>';

        if ($rows !== []) {
            foreach ($rows as $record) {
                $key = (string) $record[$this->recordKey];
                $html .= '<tr class="hover:bg-slate-50 dark:hover:bg-slate-800">';
                $html .= '<td class="border-t border-slate-200 px-5 py-3 dark:border-slate-800"><input type="checkbox" class="h-4 w-4 rounded border-slate-300"' . (in_array($key, $this->selected, true) ? ' checked' : '') . ' ylc:click="toggleSelect(\'' . $escape($key) . '\')"></td>';

                foreach ($columns as $column) {
                    $html .= '<td class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">' . $column->renderCell($record) . '</td>';
                }

                $html .= '<td class="border-t border-slate-200 px-5 py-3 text-right dark:border-slate-800">' . $this->rowActions($key, $record) . '</td></tr>';
            }
        } else {
            $colspan = count($columns) + 2;
            $html .= '<tr><td class="border-t border-slate-200 px-5 py-10 text-center text-slate-500 dark:border-slate-800 dark:text-slate-400" colspan="' . $colspan . '">No records match the current filters.</td></tr>';
        }

        $html .= '</tbody></table></div>';

        $html .= '<div class="flex flex-col gap-3 border-t border-slate-200 p-4 dark:border-slate-800 lg:flex-row lg:items-center lg:justify-between">';
        $html .= '<span class="text-sm font-bold text-slate-500 dark:text-slate-400">Showing ' . (int) $pagination['from'] . '-' . (int) $pagination['to'] . ' of ' . (int) $pagination['total'] . '</span>';
        $html .= '<div class="flex flex-wrap gap-2"><button type="button" class="' . $buttonClass . '" ylc:click="previousPage">Previous</button>';

        foreach ($pagination['pageLinks'] as $pageLink) {
            $active = $pagination['page'] === $pageLink;
            $linkClass = $active
                ? 'border-azure-600 bg-azure-600 text-white'
                : 'border-slate-200 bg-white text-slate-600 hover:border-azure-200 hover:bg-azure-50 hover:text-azure-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300';
            $html .= '<button type="button" class="h-10 min-w-10 rounded-lg border px-3 font-bold ' . $linkClass . '" ylc:click="goToPage(' . $pageLink . ')">' . $pageLink . '</button>';
        }

        $html .= '<button type="button" class="' . $buttonClass . '" ylc:click="nextPage">Next</button></div></div>';
        $html .= '</section></div>';

        if ($this->showForm) {
            $html .= $this->renderFormSlideOver();
        }

        if ($this->showView && $this->viewing) {
            $html .= $this->renderViewSlideOver($columns);
        }

        return $html;
    }

    protected function renderFormSlideOver(): string
    {
        $buttonClass = 'h-10 rounded-lg border border-slate-200 bg-white px-3 font-bold text-slate-600 hover:border-azure-200 hover:bg-azure-50 hover:text-azure-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-azure-500/40 dark:hover:bg-azure-500/10 dark:hover:text-azure-300';
        $fields = static::form(Form::make())->getFields();
        $errors = $this->getErrors();

        $html = '<div class="fixed inset-0 z-40 flex justify-end"><div class="absolute inset-0 bg-slate-950/40" ylc:click="closeForm"></div>';
        $html .= '<aside class="relative h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900">';
        $html .= '<div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-950 dark:text-white">' . ($this->editingKey ? 'Edit' : 'New') . '</h2>';
        $html .= '<button type="button" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" ylc:click="closeForm">&#10005;</button></div>';
        $html .= '<div class="mt-6 grid gap-4">';

        foreach ($fields as $field) {
            $value = $this->data[$field->getName()] ?? $field->getDefault();
            $error = $errors[$field->getName()][0] ?? null;
            $html .= $field->render($value, $error);
        }

        $html .= '<div class="mt-2 flex gap-2"><button type="button" class="h-10 flex-1 rounded-lg bg-azure-600 font-bold text-white hover:bg-azure-700" ylc:click="save">Save</button>';
        $html .= '<button type="button" class="' . $buttonClass . '" ylc:click="closeForm">Cancel</button></div>';
        $html .= '</div></aside></div>';

        return $html;
    }

    protected function renderViewSlideOver(array $columns): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $html = '<div class="fixed inset-0 z-40 flex justify-end"><div class="absolute inset-0 bg-slate-950/40" ylc:click="closeView"></div>';
        $html .= '<aside class="relative h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900">';
        $html .= '<div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-950 dark:text-white">Details</h2>';
        $html .= '<button type="button" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" ylc:click="closeView">&#10005;</button></div>';
        $html .= '<dl class="mt-6 grid gap-4 text-sm">';

        foreach ($columns as $column) {
            $html .= '<div><dt class="font-bold text-slate-500 dark:text-slate-400">' . $escape($column->getLabel()) . '</dt><dd class="mt-1 text-slate-950 dark:text-white">' . $column->renderCell($this->viewing) . '</dd></div>';
        }

        $html .= '</dl>' . $this->renderViewExtra($this->viewing) . '</aside></div>';

        return $html;
    }

    /**
     * Extra markup appended inside the view slide-over, after the field list and
     * before it closes — e.g. a record-specific action button. No-op by default.
     */
    protected function renderViewExtra(array $record): string
    {
        return '';
    }

    /**
     * Renders the per-row action buttons. Override to add/remove/replace actions
     * (e.g. a "Download" link instead of "View", or an extra custom action) —
     * this is the full markup for the cell, not a list that gets merged.
     */
    protected function rowActions(string $key, array $record): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $buttonClass = 'h-10 rounded-lg border border-slate-200 bg-white px-3 font-bold text-slate-600 hover:border-azure-200 hover:bg-azure-50 hover:text-azure-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-azure-500/40 dark:hover:bg-azure-500/10 dark:hover:text-azure-300';
        $html = '';

        if ($this->can('view', $record)) {
            $html .= '<button class="' . $buttonClass . '" type="button" ylc:click="openView(\'' . $escape($key) . '\')">View</button> ';
        }

        if ($this->isCreatable() && $this->can('update', $record)) {
            $html .= '<button class="' . $buttonClass . '" type="button" ylc:click="openEdit(\'' . $escape($key) . '\')">Edit</button> ';
        }

        if ($this->can('delete', $record)) {
            $html .= '<button class="' . $buttonClass . '" type="button" ys-confirm="Delete this record? This cannot be undone." ylc:click="deleteOne(\'' . $escape($key) . '\')">Delete</button>';
        }

        return $html;
    }

    /**
     * Renders the bulk-selection toolbar actions (next to the "N selected" label).
     * Override to add custom bulk actions alongside or instead of bulk delete.
     */
    protected function bulkActions(int $count): string
    {
        if (!$this->can('deleteAny')) {
            return '';
        }

        return '<button type="button" class="rounded-md border border-red-200 bg-white px-2.5 py-1 text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:bg-slate-900" ys-confirm="Delete ' . $count . ' selected record(s)? This cannot be undone." ylc:click="bulkDelete">Delete</button>';
    }

    /**
     * A resource with no form fields has nothing to create/edit — derived from
     * the schema so "no create button" and "no edit action" stay in sync
     * automatically for read-only resources (e.g. derived/system records).
     */
    protected function isCreatable(): bool
    {
        return static::form(Form::make())->getFields() !== [] && $this->can('create');
    }

    /**
     * Checks the current user against this resource's policy for the given
     * ability ("viewAny", "view", "create", "update", "delete", "deleteAny").
     * $record is the record array (from findRecord()/toArray()) for the
     * per-record abilities — omitted for viewAny/create/deleteAny.
     *
     * With no policy resolved (see resolvePolicyClass()), every ability is
     * allowed — authorization is opt-in per resource. Every UI entry point
     * that calls this (rowActions(), bulkActions(), isCreatable(), render())
     * is a *convenience* gate to hide what a user can't do; the real
     * enforcement is the matching check inside the action methods themselves
     * (openCreate(), openEdit(), save(), openView(), deleteOne(),
     * bulkDelete()) — those run the same check regardless of what the UI
     * rendered, since a forged AJAX call to e.g. deleteOne() skips the UI
     * entirely.
     */
    public function can(string $ability, ?array $record = null): bool
    {
        $policy = $this->policyInstance();

        if ($policy === null) {
            return true;
        }

        return $record === null ? (bool) $policy->$ability($this->currentUser()) : (bool) $policy->$ability($this->currentUser(), $record);
    }

    protected function policyInstance(): ?Policy
    {
        if ($this->policyResolved) {
            return $this->resolvedPolicy;
        }

        $this->policyResolved = true;
        $class = $this->resolvePolicyClass();
        $this->resolvedPolicy = $class !== null ? new $class() : null;

        return $this->resolvedPolicy;
    }

    /**
     * Explicit $policy wins; otherwise guesses App\Models\X -> App\Policies\
     * XPolicy (mirrors Laravel/Filament's model->policy naming convention) —
     * returns null (no policy, every ability allowed) if neither resolves to
     * a real class.
     */
    protected function resolvePolicyClass(): ?string
    {
        if ($this->policy !== null) {
            return class_exists($this->policy) ? $this->policy : null;
        }

        $guess = preg_replace('/\\\\Models\\\\/', '\\Policies\\', $this->model) . 'Policy';

        return class_exists($guess) ? $guess : null;
    }

    protected function currentUser(): mixed
    {
        return Auth::user();
    }
}
