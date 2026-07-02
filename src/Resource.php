<?php

namespace Yuga\Forge;

use Yuga\Database\Elegant\Association\BelongsTo;
use Yuga\Database\Elegant\Association\HasOne;
use Yuga\Database\Elegant\Model;
use Yuga\Forge\Actions\Action;
use Yuga\Forge\Authorization\Policy;
use Yuga\Forge\Authorization\RolePolicy;
use Yuga\Forge\Fields\Field;
use Yuga\Forge\Fields\Repeater;
use Yuga\Forge\Fields\Select;
use Yuga\Forge\Relations\RelationManager;
use Yuga\Forge\Schema\Form;
use Yuga\Forge\Schema\Section;
use Yuga\Forge\Schema\Table;
use Yuga\Forge\Schema\Tabs;
use Yuga\Live\Attributes\Url;
use Yuga\Live\Component;
use Yuga\Models\Auth;
use Yuga\Support\Inflect;

abstract class Resource extends Component
{
    /** @var class-string<\Yuga\Database\Elegant\Model> */
    protected string $model;

    protected string $recordKey = 'public_id';
    protected string $keyPrefix = 'REC';

    /** @var string[] relations to eager-load (passed straight to the model's ->with()) */
    protected array $with = [];

    /**
     * Opt-in: when true, "Delete" soft-deletes (sets the model's deleted_at
     * column) instead of removing the row, and the list gets a "Trash"
     * toggle to view/restore/permanently-delete those records. Off by
     * default - Elegant's own delete() defaults to soft-delete and will
     * silently ALTER TABLE to add deleted_at if it's missing, never what a
     * plain "Delete" button should do for a resource that hasn't opted in.
     */
    protected bool $softDeletes = false;

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
    protected array $arrayBuckets = ['data', 'filters', 'relationSearch'];

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
    public bool $showTrash = false;

    #[Url(as: 'filters', history: false)]
    public array $filters = [];

    public bool $showColumns = false;

    #[Url(as: 'columns', history: false)]
    public array $hiddenColumns = [];

    public bool $showForm = false;
    public ?string $editingKey = null;
    public array $data = [];

    /** In-flight search text per relationship-Select field name, e.g. ['customer_id' => 'jo']. */
    public array $relationSearch = [];

    public bool $showView = false;
    public ?array $viewing = null;

    /**
     * Set by mount() when a record key arrives as a mount param (see the
     * docblock there) - true means this instance is showing a dedicated
     * full page for one record (view or edit), not the list+overlay combo.
     * Not #[Url]-bound: ownership of the URL here is the app's own route
     * (a real path segment, per Hamid's call), not a query string this
     * component manages itself.
     */
    public bool $pageMode = false;

    abstract public static function form(Form $form): Form;

    abstract public static function table(Table $table): Table;

    /**
     * A consuming app that wants a dedicated page for one record (a real
     * route, not the list's overlay) passes the record key as the first
     * mount param, e.g.:
     *
     *     Route::get('/products/{key}', ...);
     *     // in the controller: ylc('admin.products-resource', [$key])
     *     Route::get('/products/{key}/edit', ...);
     *     // in the controller: ylc('admin.products-resource', [$key, 'edit'])
     *
     * Forge can't register these routes itself - it doesn't own routing in
     * the consuming app - so this is a convention to opt into, not
     * something that happens automatically. See listUrl()/recordUrl()/
     * editUrl() for the matching URL conventions used to navigate back out.
     */
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

        if (isset($params[0]) && is_string($params[0]) && $params[0] !== '') {
            $this->pageMode = true;

            if (($params[1] ?? null) === 'edit') {
                $this->openEdit($params[0]);
            } else {
                $this->openView($params[0]);
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

        // "filters"'s declared static default is [] (an empty array literal,
        // since a schema-driven property can't declare per-filter defaults
        // in its property declaration) - the real "untouched" shape is one
        // entry per filter with that filter's own default, computed in
        // mount(). Substitute it the same way "sort" already is, so the URL
        // stays clean when every filter is still at its default.
        if (isset($properties['filters'])) {
            $defaults = [];

            foreach (static::table(Table::make())->getFilters() as $filter) {
                $defaults[$filter->getName()] = $filter->getDefault();
            }

            $properties['filters']['default'] = $defaults;
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

    public function toggleTrash(): void
    {
        $this->showTrash = !$this->showTrash;
        $this->page = 1;
        $this->selected = [];
    }

    public function addRepeaterRow(string $field): void
    {
        if (!is_array($this->data[$field] ?? null)) {
            $this->data[$field] = [];
        }

        $this->data[$field][] = $this->repeaterRowDefaults($field);
    }

    public function removeRepeaterRow(string $field, int $index): void
    {
        if (!is_array($this->data[$field] ?? null)) {
            return;
        }

        array_splice($this->data[$field], $index, 1);
    }

    protected function repeaterRowDefaults(string $fieldName): array
    {
        foreach (static::form(Form::make())->getFields() as $field) {
            if ($field instanceof Repeater && $field->getName() === $fieldName) {
                $row = [];

                foreach ($field->getFields() as $subField) {
                    $row[$subField->getName()] = $subField->getDefault();
                }

                return $row;
            }
        }

        return [];
    }

    public function clearFilters(): void
    {
        $this->search = '';

        foreach (static::table(Table::make())->getFilters() as $filter) {
            $this->filters[$filter->getName()] = $filter->getDefault();
        }

        $this->page = 1;
    }

    public function toggleColumns(): void
    {
        $this->showColumns = !$this->showColumns;
    }

    public function toggleColumn(string $name): void
    {
        if (in_array($name, $this->hiddenColumns, true)) {
            $this->hiddenColumns = array_values(array_diff($this->hiddenColumns, [$name]));
        } else {
            $this->hiddenColumns[] = $name;
        }
    }

    public function resetColumns(): void
    {
        $this->hiddenColumns = [];
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

    /**
     * $redirectKey overrides what page-mode redirects to on close - needed
     * for the just-created case, where editingKey was never set (there was
     * nothing to edit yet) but the new record now has a real key to send
     * the user to instead of back to the bare list. Anything UI-triggered
     * (ylc:click="closeForm") just omits it and falls back to editingKey.
     */
    public function closeForm(?string $redirectKey = null): void
    {
        $key = $redirectKey ?? $this->editingKey;
        $this->resetForm();
        $this->showForm = false;

        if ($this->pageMode) {
            $this->redirect($key !== null ? $this->recordUrl($key) : $this->listUrl());
        }
    }

    protected function resetForm(): void
    {
        $this->editingKey = null;
        $this->data = [];
        $this->relationSearch = [];

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
            if (!$field->isVisible($this->data)) {
                continue;
            }

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
            $this->closeForm();
        } else {
            $key = $this->generateKey();
            $payload[$this->recordKey] = $key;
            $payload['created_at'] = $this->now();

            ($this->model)::create($payload);

            $this->toast('Record created.');
            $this->afterSave(true, $key);
            // editingKey is still null here (there was nothing to edit) -
            // pass the brand new key explicitly so page mode redirects to
            // it instead of falling back to the bare list.
            $this->closeForm($key);
        }
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

        // Unlike closeForm(), there's no "go to the record" option here -
        // closing a view *is* leaving the record, so page mode always goes
        // back to the list.
        if ($this->pageMode) {
            $this->redirect($this->listUrl());
        }
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
        // plain "Delete" confirm button means for a resource that hasn't
        // opted into $softDeletes. Resources that have opted in get the
        // real soft delete instead, so the record lands in the trash view
        // rather than disappearing outright.
        ($this->model)::where($this->recordKey, $key)->delete(!$this->softDeletes);
        $this->selected = array_values(array_diff($this->selected, [$key]));
        $this->toast($this->softDeletes ? 'Record moved to trash.' : 'Record deleted.');

        // The record this page was dedicated to no longer exists - nothing
        // left to render here, so leave for the list instead.
        if ($this->pageMode) {
            $this->redirect($this->listUrl());
        }
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

            ($this->model)::where($this->recordKey, $key)->delete(!$this->softDeletes);
            $deleted++;
        }

        $this->toast($deleted . ($this->softDeletes ? ' record(s) moved to trash.' : ' record(s) deleted.'));
        $this->selected = [];
    }

    /**
     * Trash-view-only actions - undo a soft delete, or actually remove the
     * row for good. Both no-op (silently) if $softDeletes isn't enabled,
     * same defensive stance as every other action here: a stray click from
     * a stale/cached UI shouldn't do something a resource didn't opt into.
     */
    public function restoreOne(string $key): void
    {
        if (!$this->softDeletes) {
            return;
        }

        $record = $this->findRecord($key);

        if (!$record || !$this->can('update', $record)) {
            return;
        }

        $deleteKey = (new ($this->model)())->getDeleteKey();

        ($this->model)::where($this->recordKey, $key)->update([$deleteKey => null]);
        $this->selected = array_values(array_diff($this->selected, [$key]));
        $this->toast('Record restored.');
    }

    public function bulkRestore(): void
    {
        if (!$this->softDeletes || empty($this->selected)) {
            return;
        }

        $deleteKey = (new ($this->model)())->getDeleteKey();
        $restored = 0;

        foreach ($this->selected as $key) {
            $record = $this->findRecord($key);

            if (!$record || !$this->can('update', $record)) {
                continue;
            }

            ($this->model)::where($this->recordKey, $key)->update([$deleteKey => null]);
            $restored++;
        }

        $this->toast($restored . ' record(s) restored.');
        $this->selected = [];
    }

    public function forceDeleteOne(string $key): void
    {
        if (!$this->softDeletes) {
            return;
        }

        $record = $this->findRecord($key);

        if (!$record || !$this->can('delete', $record)) {
            return;
        }

        ($this->model)::where($this->recordKey, $key)->delete(true);
        $this->selected = array_values(array_diff($this->selected, [$key]));
        $this->toast('Record permanently deleted.');
    }

    public function bulkForceDelete(): void
    {
        if (!$this->softDeletes || empty($this->selected)) {
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

        $this->toast($deleted . ' record(s) permanently deleted.');
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

        // Elegant's own trashed-row filtering (Builder::deletable()) only
        // ever applies a NOT NULL filter when onlyTrashed() was explicitly
        // called - it has no default "exclude trashed unless told
        // otherwise" behavior, so a soft-deleted row would otherwise still
        // show up in the regular list. Filtered explicitly here instead of
        // relying on that.
        if ($this->softDeletes) {
            $deleteColumn = $instance->getTable() . '.' . $instance->getDeleteKey();
            $this->showTrash ? $query->whereNotNull($deleteColumn) : $query->whereNull($deleteColumn);
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

    /**
     * A narrower sibling to relationJoin() for Select::relationship()'s
     * live search - just the related model class/table, not full JOIN
     * metadata (relationJoin() also needs foreignKey/otherKey for an ON
     * clause, a different shape; left untouched rather than reused here).
     *
     * @return array{class: class-string<Model>, table: string}|null
     */
    protected function resolveRelatedModel(Model $instance, string $relation): ?array
    {
        if (!method_exists($instance, $relation)) {
            return null;
        }

        $association = $instance->$relation();

        if (!$association instanceof BelongsTo && !$association instanceof HasOne) {
            return null;
        }

        $reflection = new \ReflectionObject($association);
        $prop = $reflection->getProperty('child');
        $prop->setAccessible(true);
        $related = $prop->getValue($association);

        return ['class' => get_class($related), 'table' => $related->getTable()];
    }

    /**
     * Up to $field->getSearchLimit() rows from the related table whose
     * title column LIKE %query%, for a relationship Select's combobox.
     * Empty query returns the first N rows unfiltered, so the dropdown
     * shows something on open rather than staying blank until typing
     * starts.
     *
     * @return array<int, array{id: mixed, label: string}>
     */
    protected function relationSearchResults(Select $field): array
    {
        $instance = new ($this->model)();
        $resolved = $this->resolveRelatedModel($instance, $field->getRelation());

        if ($resolved === null) {
            return [];
        }

        $modelClass = $resolved['class'];
        $titleColumn = $field->getTitleColumn();
        $term = trim($this->relationSearch[$field->getName()] ?? '');

        $query = $term !== ''
            ? $modelClass::where($titleColumn, 'like', '%' . $term . '%')
            : $modelClass::limit($field->getSearchLimit());

        $rows = $query->limit($field->getSearchLimit())->get()->toArray();
        $primaryKey = (new $modelClass())->getPrimaryKey();

        return array_map(
            fn (array $row) => ['id' => $row[$primaryKey], 'label' => (string) $row[$titleColumn]],
            $rows
        );
    }

    /**
     * Resolves a relationship Select's currently-stored FK value back to
     * its display label - for showing "Asha Patel" instead of a raw id,
     * both in edit mode on first render and right after a selection.
     */
    protected function relationRecordLabel(Select $field, mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        $instance = new ($this->model)();
        $resolved = $this->resolveRelatedModel($instance, $field->getRelation());

        if ($resolved === null) {
            return null;
        }

        $modelClass = $resolved['class'];
        $related = new $modelClass();
        $row = $modelClass::where($related->getPrimaryKey(), $id)->first()?->toArray();

        return $row ? (string) $row[$field->getTitleColumn()] : null;
    }

    /**
     * The relationship Select's "pick this one" action - clears that
     * field's in-flight search text so the resolved label shows again
     * immediately instead of the stale query.
     */
    public function selectRelationOption(string $field, string $id): void
    {
        $this->data[$field] = $id;
        unset($this->relationSearch[$field]);
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

    /** @var string The table notify() inserts into. Override if your app uses a different one. */
    protected string $notificationsTable = 'notifications';

    /**
     * Inserts a row into the app's notifications table and emits "notify" so
     * any listening component (a notifications bell, etc.) picks it up -
     * the exact boilerplate every resource that wants this otherwise repeats
     * by hand in afterSave()/a custom action. $url/$type default from the
     * resource itself (see notificationUrl()/notificationType()) but can be
     * overridden per call.
     */
    protected function notify(string $title, string $message, ?string $url = null, ?string $type = null): void
    {
        db($this->notificationsTable)->insert([
            'public_id' => 'NTF-' . uniqid(),
            'type' => $type ?? $this->notificationType(),
            'title' => $title,
            'message' => $message,
            'url' => $url ?? $this->notificationUrl(),
            'created_at' => $this->now(),
        ]);

        $this->emit('notify');
    }

    /**
     * Default notification "type" tag: the resource's label, singularized
     * and lowercased (e.g. "ProductsResource" -> "product").
     */
    protected function notificationType(): string
    {
        return strtolower(Inflect::singularize($this->label()));
    }

    /**
     * Default notification URL - now just an alias for listUrl(), kept
     * separate in case a future resource wants notifications to point
     * somewhere other than its own list (this one rarely needs overriding
     * on its own; override listUrl() instead unless notifications
     * specifically need to differ).
     */
    protected function notificationUrl(): string
    {
        return $this->listUrl();
    }

    /**
     * This resource's own list page. Assumes /admin/{kebab-case label}
     * (true for every resource in this app) - override if yours lives
     * somewhere else. Used as the "back to list" target in page mode, as
     * the default notification URL, and (public, unlike every other URL
     * helper here) by ForgeServiceProvider::boot() to auto-register
     * dedicated page routes from config('forge.pages') - see its docblock.
     */
    public function listUrl(): string
    {
        return host('admin/' . strtolower(str_replace(' ', '-', $this->label())));
    }

    /**
     * A single record's dedicated view page, e.g. "/admin/products/PRD-201"
     * - only meaningful if the consuming app actually registered a matching
     * route (see mount()'s docblock); override if yours uses a different
     * pattern (a different param name, a nested prefix, etc).
     */
    protected function recordUrl(string $key): string
    {
        return $this->listUrl() . '/' . rawurlencode($key);
    }

    /**
     * A single record's dedicated edit page, e.g. "/admin/products/PRD-201/edit".
     */
    protected function editUrl(string $key): string
    {
        return $this->recordUrl($key) . '/edit';
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

        if ($this->pageMode) {
            if ($this->showForm) {
                return $this->renderFormPage();
            }

            if ($this->showView && $this->viewing) {
                return $this->renderViewPage(static::table(Table::make()));
            }

            return $this->renderPageNotFound();
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

    /**
     * pageMode was set (a record key arrived via mount()) but neither
     * openView() nor openEdit() left anything to show - the key didn't
     * match a record, or the policy denied it. Still offers the way back
     * out a slide-over/modal gets for free (it never leaves the list page
     * at all) - a dedicated page needs its own link back.
     */
    protected function renderPageNotFound(): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        return '<div class="grid min-w-0 gap-5">'
            . '<a href="' . $escape($this->listUrl()) . '" class="text-sm font-bold text-azure-600 hover:underline">&larr; Back to ' . $escape($this->label()) . '</a>'
            . '<div class="grid place-items-center rounded-lg border border-slate-200 bg-white p-12 text-center text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20 dark:text-slate-400">Record not found, or you don\'t have permission to view it.</div>'
            . '</div>';
    }

    /**
     * 'slideover' (the default) or 'modal' - which chrome wrapPanel() uses
     * for the create/edit form and the "Details" view when neither is
     * showing as a dedicated page. Override per resource; this is a static
     * per-resource choice, unrelated to pageMode (a resource can use modals
     * for its quick inline view/edit *and* support dedicated pages - they
     * don't conflict, pageMode always wins when a key arrived via mount()).
     */
    protected function panelDisplay(): string
    {
        return 'slideover';
    }

    /**
     * 'sm'|'md' (default)|'lg'|'xl'|'2xl' - how wide the panel is, whether
     * it's a slide-over or a modal. A resource with a lot of fields (or
     * Sections laid out in 2+ columns - those need real width to be worth
     * it at all) will want something wider than the default.
     */
    protected function panelWidth(): string
    {
        return 'md';
    }

    /**
     * A fixed set of literal classes, not a dynamically built
     * "max-w-{$size}" string - same Tailwind-literal-scanning reason as
     * every other size/columns option in Forge.
     */
    protected function panelWidthClass(): string
    {
        return match ($this->panelWidth()) {
            'sm' => 'max-w-sm',
            'lg' => 'max-w-lg',
            'xl' => 'max-w-xl',
            '2xl' => 'max-w-2xl',
            default => 'max-w-md',
        };
    }

    /**
     * Wraps $inner (the form or view's own content - heading, fields/dl,
     * buttons) in either slide-over or modal chrome, per panelDisplay().
     * $closeAction is whatever ylc:click target closes it (closeForm() or
     * closeView()) - both already know how to redirect instead when
     * pageMode is set, so this never needs to care about that itself.
     */
    protected function wrapPanel(string $inner, string $closeAction): string
    {
        $widthClass = $this->panelWidthClass();

        if ($this->panelDisplay() === 'modal') {
            return '<div class="fixed inset-0 z-40 flex items-center justify-center p-4">'
                . '<div class="absolute inset-0 bg-slate-950/40" ylc:click="' . $closeAction . '"></div>'
                . '<div class="relative max-h-[90vh] w-full ' . $widthClass . ' overflow-y-auto rounded-lg bg-white p-6 shadow-2xl dark:bg-slate-900">'
                . $inner
                . '</div></div>';
        }

        return '<div class="fixed inset-0 z-40 flex justify-end">'
            . '<div class="absolute inset-0 bg-slate-950/40" ylc:click="' . $closeAction . '"></div>'
            . '<aside class="relative h-full w-full ' . $widthClass . ' overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900">'
            . $inner
            . '</aside></div>';
    }

    /**
     * A dedicated full page for one record - no overlay/backdrop, just a
     * plain card in the normal content flow, with a real link back to the
     * list instead of a ylc:click close action (there's no overlay state
     * to toggle off; leaving the page IS closing it).
     */
    protected function renderRecordPage(string $inner): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        return '<div class="grid min-w-0 gap-5">'
            . '<a href="' . $escape($this->listUrl()) . '" class="text-sm font-bold text-azure-600 hover:underline">&larr; Back to ' . $escape($this->label()) . '</a>'
            . '<section class="min-w-0 max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">'
            . $inner
            . '</section></div>';
    }

    protected function renderFormPage(): string
    {
        return $this->renderRecordPage($this->renderFormContent());
    }

    protected function renderViewPage(Table $table): string
    {
        return $this->renderRecordPage($this->renderViewContent($table->getColumns()));
    }

    protected function renderPage(Table $table, array $pagination): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $label = $this->label();
        $buttonClass = 'h-10 rounded-lg border border-slate-200 bg-white px-3 font-bold text-slate-600 hover:border-azure-200 hover:bg-azure-50 hover:text-azure-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-azure-500/40 dark:hover:bg-azure-500/10 dark:hover:text-azure-300';
        $inputClass = 'h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-slate-950 outline-none focus:border-azure-600 focus:ring-4 focus:ring-azure-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-azure-400 dark:focus:ring-azure-500/20';

        $columns = $table->getColumns();
        $filters = $table->getFilters();
        $toggleableColumns = array_values(array_filter($columns, fn ($column) => $column->isToggleable()));
        $visibleColumns = array_values(array_filter(
            $columns,
            fn ($column) => !$column->isToggleable() || !in_array($column->getName(), $this->hiddenColumns, true)
        ));
        $rows = $pagination['rows'];
        $pageKeys = array_column($rows, $this->recordKey);
        $jsKeys = fn (array $keys) => '[' . implode(',', array_map(fn ($key) => "'" . addslashes((string) $key) . "'", $keys)) . ']';
        $allSelected = $pageKeys !== [] && count(array_intersect($pageKeys, $this->selected)) === count($pageKeys);

        $html = '<div class="grid min-w-0 gap-5">';

        $html .= '<header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">';
        $html .= '<div><span class="text-xs font-extrabold uppercase text-azure-600">' . $escape($label) . '</span>';
        $html .= '<h1 class="mt-1 text-3xl font-bold leading-tight text-slate-950 dark:text-white">' . $escape($label) . '</h1></div>';

        $html .= '<div class="flex flex-wrap items-center gap-2">';

        if ($this->isCreatable() && !$this->showTrash) {
            $html .= '<button type="button" class="h-10 rounded-lg bg-azure-600 px-4 font-bold text-white shadow-sm hover:bg-azure-700" ylc:click="openCreate">+ New</button> ';
        }

        $html .= $this->renderHeaderActions();

        $html .= '</div>';

        $html .= '</header>';

        $html .= '<section class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-lg shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">';
        $html .= '<div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">';
        $html .= '<div class="flex flex-1 flex-col gap-2.5 sm:flex-row sm:items-center">';
        $html .= '<input class="' . $inputClass . ' sm:w-72" type="search" placeholder="Search" value="' . $escape($this->search) . '" ylc:model="search">';

        if ($filters !== []) {
            $html .= '<button type="button" class="' . $buttonClass . '" ylc:click="toggleFilters">' . ($this->showFilters ? 'Hide filters' : 'Filters') . '</button>';
        }

        if ($toggleableColumns !== []) {
            $html .= '<button type="button" class="' . $buttonClass . '" ylc:click="toggleColumns">' . ($this->showColumns ? 'Hide columns' : 'Columns') . '</button>';
        }

        if ($this->softDeletes) {
            $html .= '<button type="button" class="' . $buttonClass . '" ylc:click="toggleTrash">' . ($this->showTrash ? 'Back to list' : 'Trash') . '</button>';
        }

        $html .= '</div>';

        if ($this->selected !== []) {
            $count = count($this->selected);
            $html .= '<div class="flex items-center gap-2 rounded-lg bg-azure-50 px-3 py-2 text-sm font-bold text-azure-700 dark:bg-azure-500/10 dark:text-azure-200">';
            $html .= '<span>' . $count . ' selected</span>';
            $html .= $this->renderBulkActions($count);
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

        if ($this->showColumns && $toggleableColumns !== []) {
            $html .= '<div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950 sm:flex-row sm:flex-wrap sm:items-center">';

            foreach ($toggleableColumns as $column) {
                $name = $column->getName();
                $checked = !in_array($name, $this->hiddenColumns, true);
                $html .= '<label class="flex items-center gap-2 text-sm font-bold text-slate-600 dark:text-slate-300"><input type="checkbox" class="h-4 w-4 rounded border-slate-300"' . ($checked ? ' checked' : '') . ' ylc:click="toggleColumn(\'' . $escape($name) . '\')">' . $escape($column->getLabel()) . '</label>';
            }

            $html .= '<button type="button" class="' . $buttonClass . ' mt-auto w-fit" ylc:click="resetColumns">Reset columns</button>';
            $html .= '</div>';
        }

        $html .= '<div class="overflow-x-auto"><table class="w-full min-w-[640px]"><thead><tr class="bg-slate-50 dark:bg-slate-900">';
        $html .= '<th class="w-10 border-t border-slate-200 px-5 py-3 dark:border-slate-800"><input type="checkbox" class="h-4 w-4 rounded border-slate-300"' . ($allSelected ? ' checked' : '') . ' ylc:click="' . ($allSelected ? 'clearSelection' : 'selectPage(' . $jsKeys($pageKeys) . ')') . '"></th>';

        foreach ($visibleColumns as $column) {
            $html .= '<th class="border-t border-slate-200 px-5 py-3 text-left text-xs font-bold uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">' . $column->renderHeader($this->sort, $this->direction) . '</th>';
        }

        $html .= '<th class="border-t border-slate-200 px-5 py-3 text-right text-xs font-bold uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">Actions</th>';
        $html .= '</tr></thead><tbody>';

        if ($rows !== []) {
            foreach ($rows as $record) {
                $key = (string) $record[$this->recordKey];
                $html .= '<tr class="hover:bg-slate-50 dark:hover:bg-slate-800">';
                $html .= '<td class="border-t border-slate-200 px-5 py-3 dark:border-slate-800"><input type="checkbox" class="h-4 w-4 rounded border-slate-300"' . (in_array($key, $this->selected, true) ? ' checked' : '') . ' ylc:click="toggleSelect(\'' . $escape($key) . '\')"></td>';

                foreach ($visibleColumns as $column) {
                    $html .= '<td class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">' . $column->renderCell($record) . '</td>';
                }

                $html .= '<td class="border-t border-slate-200 px-5 py-3 text-right dark:border-slate-800">' . $this->renderRowActions($key, $record) . '</td></tr>';
            }
        } else {
            $colspan = count($visibleColumns) + 2;
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
        return $this->wrapPanel($this->renderFormContent(), 'closeForm');
    }

    /**
     * The form panel's heading - override for something like
     * "Edit {$this->data['name']}" instead of the generic Edit/New. The
     * record itself isn't loaded as a model here, only $this->data (already
     * populated by openEdit()/openCreate() by the time this renders).
     */
    protected function formTitle(): string
    {
        return $this->editingKey ? 'Edit' : 'New';
    }

    /**
     * The form's own content - heading, fields/sections, save/cancel - with
     * no opinion about what wraps it. Shared by the slide-over/modal chrome
     * (wrapPanel()) and the dedicated full-page mode (renderFormPage()).
     */
    protected function renderFormContent(): string
    {
        $buttonClass = 'h-10 rounded-lg border border-slate-200 bg-white px-3 font-bold text-slate-600 hover:border-azure-200 hover:bg-azure-50 hover:text-azure-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-azure-500/40 dark:hover:bg-azure-500/10 dark:hover:text-azure-300';
        $schema = static::form(Form::make())->getSchema();
        $errors = $this->getErrors();

        $html = '<div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-950 dark:text-white">' . htmlspecialchars($this->formTitle(), ENT_QUOTES, 'UTF-8') . '</h2>';
        $html .= '<button type="button" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" ylc:click="closeForm">&#10005;</button></div>';
        $html .= '<div class="mt-6 grid gap-4">';

        foreach ($schema as $item) {
            $html .= match (true) {
                $item instanceof Section => $this->renderFormSection($item, $errors),
                $item instanceof Tabs => $this->renderFormTabs($item, $errors),
                default => $this->renderFormField($item, $errors),
            };
        }

        $html .= '<div class="mt-2 flex gap-2"><button type="button" class="h-10 flex-1 rounded-lg bg-azure-600 font-bold text-white hover:bg-azure-700" ylc:click="save">Save</button>';
        $html .= '<button type="button" class="' . $buttonClass . '" ylc:click="closeForm">Cancel</button></div>';
        $html .= '</div>';

        return $html;
    }

    protected function renderFormField(Field $field, array $errors): string
    {
        if (!$field->isVisible($this->data)) {
            return '';
        }

        $value = $this->data[$field->getName()] ?? $field->getDefault();
        $error = $errors[$field->getName()][0] ?? null;

        if ($field instanceof Select && $field->isRelationship()) {
            return $this->renderRelationSelectField($field, $value, $error);
        }

        return $field->render($value, $error);
    }

    /**
     * Field::render() normally owns this <label> shell - duplicated here
     * rather than reused because the combobox markup itself needs DB
     * access (search results, resolved label) that only Resource has,
     * unlike every other field whose renderInput() is fully self-contained.
     */
    protected function renderRelationSelectField(Select $field, mixed $value, ?string $error): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $label = $escape($field->getLabel());
        $input = $this->renderRelationCombobox($field, $value);
        $errorHtml = $error ? '<small class="font-medium text-red-600">' . $escape($error) . '</small>' : '';

        return <<<HTML
            <label class="grid gap-1.5">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{$label}</span>
                {$input}
                {$errorHtml}
            </label>
            HTML;
    }

    protected function renderRelationCombobox(Select $field, mixed $value): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $name = $field->getName();
        $searchText = $this->relationSearch[$name] ?? '';
        $displayValue = $searchText !== '' ? $searchText : ($this->relationRecordLabel($field, $value) ?? '');
        $results = $this->relationSearchResults($field);

        $resultsHtml = '';

        foreach ($results as $result) {
            $resultsHtml .= '<button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-azure-50 dark:hover:bg-azure-500/10"'
                . ' ys-on:click="close()"'
                . ' ylc:click="selectRelationOption(\'' . addslashes($name) . '\', \'' . addslashes((string) $result['id']) . '\')">'
                . $escape($result['label']) . '</button>';
        }

        if ($results === []) {
            $resultsHtml = '<div class="px-3 py-2 text-sm text-slate-400">No results</div>';
        }

        // data-server-value tells morph.js to honour the server's
        // value= attribute instead of preserving whatever the user
        // previously typed - only when search text is blank (fresh open
        // or just after a selection cleared the search term), since THEN
        // the server's resolved label is what should show. When the user
        // IS actively typing ($searchText !== ''), the normal input-
        // preservation behaviour should apply so typing isn't reset.
        $serverValueAttr = $searchText === '' ? ' data-server-value' : '';

        return '<div ys-component="headless-combobox" class="relative" ys-on:click.outside="close()">'
            . '<input type="text" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-slate-950 outline-none focus:border-azure-600 focus:ring-4 focus:ring-azure-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-azure-400 dark:focus:ring-azure-500/20"'
            . ' value="' . $escape($displayValue) . '"'
            . $serverValueAttr
            . ' ylc:model="relationSearch.' . $escape($name) . '"'
            . ' ys-on:click="show()">'
            . '<input type="hidden" value="' . $escape((string) $value) . '" data-server-value ylc:model="data.' . $escape($name) . '">'
            . '<div class="absolute z-10 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900" ys-show="open">'
            . $resultsHtml
            . '</div></div>';
    }

    protected function renderFormSection(Section $section, array $errors): string
    {
        $collapsible = $section->isCollapsible() && $section->getHeading() !== null;
        $disclosureAttrs = $collapsible
            ? ' ys-component="headless-disclosure" ys-props="{ defaultOpen: ' . ($section->isCollapsed() ? 'false' : 'true') . ' }"'
            : '';

        $html = '<div class="grid gap-4 rounded-lg border border-slate-200 p-4 dark:border-slate-800"' . $disclosureAttrs . '>';
        $html .= $this->renderSectionHeading($section, $collapsible);
        $html .= '<div class="grid gap-4 ' . $this->sectionGridClass($section->getColumns()) . '"' . ($collapsible ? ' ys-show="open"' : '') . '>';

        foreach ($section->getFields() as $field) {
            $html .= $this->renderFormField($field, $errors);
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Tab switching is pure client-side - delegated to YS's "headless-tabs"
     * component (YS.use(YS.headless) in the admin layout) instead of
     * hand-rolled onclick/classList JS. YS keeps the component's scope
     * (__ysScope) on the DOM node itself and skips re-initializing it on
     * morph (__ysInitialized guard), so "which tab is open" survives a
     * YLC re-render with no preserve/restore step needed on either side.
     */
    protected function renderFormTabs(Tabs $tabs, array $errors): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $buttons = '';
        $panels = '';

        foreach ($tabs->getTabs() as $index => $tab) {
            $buttonClass = "{ 'border-azure-600 text-azure-600': isSelected({$index}), 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400': !isSelected({$index}) }";

            $buttons .= '<button type="button" ys-on:click="select(' . $index . ')" ys-class="' . $escape($buttonClass) . '" class="-mb-px border-b-2 px-3 py-2 text-sm font-bold">' . $escape($tab->getLabel()) . '</button>';

            $fieldsHtml = '';

            foreach ($tab->getFields() as $field) {
                $fieldsHtml .= $this->renderFormField($field, $errors);
            }

            $panels .= '<div ys-show="isSelected(' . $index . ')" class="grid gap-4 pt-4">' . $fieldsHtml . '</div>';
        }

        return '<div ys-component="headless-tabs" ys-props="{ defaultValue: 0 }">'
            . '<div class="flex gap-1 border-b border-slate-200 dark:border-slate-800">' . $buttons . '</div>'
            . $panels
            . '</div>';
    }

    protected function renderViewSlideOver(array $columns): string
    {
        return $this->wrapPanel($this->renderViewContent($columns), 'closeView');
    }

    /**
     * The "Details" view panel's heading - override for something like
     * "{$this->viewing['name']}" instead of the generic "Details".
     * $this->viewing holds the raw record by the time this renders
     * (populated by openView()).
     */
    protected function viewTitle(): string
    {
        return 'Details';
    }

    /**
     * The "Details" view's own content - heading, field/column list,
     * sections, relation managers, the renderViewExtra() hook - with no
     * opinion about what wraps it. Shared by the slide-over/modal chrome
     * (wrapPanel()) and the dedicated full-page mode (renderViewPage()).
     */
    protected function renderViewContent(array $columns): string
    {
        $html = '<div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-950 dark:text-white">' . htmlspecialchars($this->viewTitle(), ENT_QUOTES, 'UTF-8') . '</h2>';
        $html .= '<button type="button" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" ylc:click="closeView">&#10005;</button></div>';
        $html .= '<dl class="mt-6 grid gap-4 text-sm">';

        $shown = [];

        foreach ($columns as $column) {
            $shown[] = $column->getName();
            $html .= $this->renderViewItem($column->getLabel(), $column->renderCell($this->viewing));
        }

        $sectionsHtml = '';

        // A column only covers what's already shown in the table - form
        // fields/sections with no matching column (e.g. a description or
        // image that's not worth a dedicated list column) would otherwise
        // be invisible here even though they're genuinely saved. Sections
        // render as their own bordered block after the main list, the same
        // visual grouping the form itself uses - not folded into this <dl>.
        foreach (static::form(Form::make())->getSchema() as $item) {
            if ($item instanceof Section) {
                $sectionsHtml .= $this->renderViewSection($item, $shown);
                continue;
            }

            if ($item instanceof Tabs) {
                $sectionsHtml .= $this->renderViewTabs($item, $shown);
                continue;
            }

            if ($item->isHiddenField() || in_array($item->getName(), $shown, true) || !$item->isVisible($this->viewing)) {
                continue;
            }

            $value = $this->viewing[$item->getName()] ?? null;
            $html .= $this->renderViewItem($item->getLabel(), $this->renderFieldDisplay($item, $value));
        }

        $html .= '</dl>' . $sectionsHtml;

        foreach ($this->relationManagers() as $manager) {
            $html .= $this->renderRelationManager($manager);
        }

        $html .= $this->renderViewExtra($this->viewing);

        return $html;
    }

    protected function renderViewItem(string $label, string $valueHtml): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        return '<div><dt class="font-bold text-slate-500 dark:text-slate-400">' . $escape($label) . '</dt><dd class="mt-1 text-slate-950 dark:text-white">' . $valueHtml . '</dd></div>';
    }

    /**
     * Field::renderDisplay()'s relationship-aware counterpart - a
     * relationship Select has no $options map to look up against, so its
     * raw FK value needs resolving back to a label via DB access Field
     * doesn't have. Every "Details" view field-list loop should call this
     * instead of $field->renderDisplay($value) directly.
     */
    protected function renderFieldDisplay(Field $field, mixed $value): string
    {
        if ($field instanceof Select && $field->isRelationship()) {
            $label = $this->relationRecordLabel($field, $value);

            return $label === null ? '&mdash;' : htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        }

        return $field->renderDisplay($value);
    }

    /**
     * Renders one Section as its own bordered block in the "Details" view -
     * the read-only counterpart to renderFormSection(). $shown is the set of
     * field names already covered by a table column (skipped here too, same
     * reasoning as the flat fields loop in renderViewSlideOver()). Returns ''
     * if every field in the section was already shown elsewhere, rather than
     * an empty bordered box with just a heading.
     */
    protected function renderViewSection(Section $section, array $shown): string
    {
        $items = '';

        foreach ($section->getFields() as $field) {
            if ($field->isHiddenField() || in_array($field->getName(), $shown, true) || !$field->isVisible($this->viewing)) {
                continue;
            }

            $value = $this->viewing[$field->getName()] ?? null;
            $items .= $this->renderViewItem($field->getLabel(), $this->renderFieldDisplay($field, $value));
        }

        if ($items === '') {
            return '';
        }

        $collapsible = $section->isCollapsible() && $section->getHeading() !== null;
        $disclosureAttrs = $collapsible
            ? ' ys-component="headless-disclosure" ys-props="{ defaultOpen: ' . ($section->isCollapsed() ? 'false' : 'true') . ' }"'
            : '';

        $html = '<div class="mt-2 grid gap-4 rounded-lg border border-slate-200 p-4 dark:border-slate-800"' . $disclosureAttrs . '>';
        $html .= $this->renderSectionHeading($section, $collapsible);
        $html .= '<dl class="grid gap-4 text-sm ' . $this->sectionGridClass($section->getColumns()) . '"' . ($collapsible ? ' ys-show="open"' : '') . '>' . $items . '</dl></div>';

        return $html;
    }

    /**
     * Details is read-only, so unlike the form there's no interactive tab
     * switching to wire up - each Tab just renders as its own bordered
     * block (one per tab, all shown at once), the same treatment Sections
     * already get here.
     */
    protected function renderViewTabs(Tabs $tabs, array $shown): string
    {
        $html = '';

        foreach ($tabs->getTabs() as $tab) {
            $items = '';

            foreach ($tab->getFields() as $field) {
                if ($field->isHiddenField() || in_array($field->getName(), $shown, true) || !$field->isVisible($this->viewing)) {
                    continue;
                }

                $value = $this->viewing[$field->getName()] ?? null;
                $items .= $this->renderViewItem($field->getLabel(), $this->renderFieldDisplay($field, $value));
            }

            if ($items === '') {
                continue;
            }

            $html .= '<div class="mt-2 grid gap-4 rounded-lg border border-slate-200 p-4 dark:border-slate-800">';
            $html .= '<h3 class="text-sm font-bold text-slate-950 dark:text-white">' . htmlspecialchars($tab->getLabel(), ENT_QUOTES, 'UTF-8') . '</h3>';
            $html .= '<dl class="grid gap-4 text-sm">' . $items . '</dl></div>';
        }

        return $html;
    }

    /**
     * Shared between renderFormSection() and renderViewSection() - a
     * Section's heading/description are both optional (a Section can be
     * just a grouping/column-layout device with no label at all), so this
     * renders '' when there's nothing to show rather than an empty heading
     * wrapper.
     */
    /**
     * $collapsible is resolved by the caller (Section::isCollapsible() AND
     * a heading actually being set - nothing to click otherwise), not
     * re-checked here, so this only ever has to decide how to render.
     */
    protected function renderSectionHeading(Section $section, bool $collapsible = false): string
    {
        $heading = $section->getHeading();
        $description = $section->getDescription();

        if ($heading === null && $description === null) {
            return '';
        }

        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $inner = '<div>';

        if ($heading !== null) {
            $inner .= '<h3 class="font-bold text-slate-950 dark:text-white">' . $escape($heading) . '</h3>';
        }

        if ($description !== null) {
            $inner .= '<p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">' . $escape($description) . '</p>';
        }

        $inner .= '</div>';

        if (!$collapsible) {
            return $inner;
        }

        return '<button type="button" class="flex w-full items-center justify-between gap-3 text-left" ys-on:click="toggle()">'
            . $inner
            . '<span class="shrink-0 text-slate-400 transition-transform" ys-class="{ \'rotate-180\': open }">&#9662;</span>'
            . '</button>';
    }

    /**
     * A fixed set of literal classes, not a dynamically built
     * "grid-cols-{$n}" string - Tailwind only generates CSS for classes it
     * finds as literal text while scanning source files (hit this bug
     * pattern more than once already this session).
     */
    protected function sectionGridClass(int $columns): string
    {
        return match ($columns) {
            2 => 'sm:grid-cols-2',
            3 => 'sm:grid-cols-3',
            4 => 'sm:grid-cols-4',
            default => 'grid-cols-1',
        };
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
     * Related records to show inline in the "Details" view slide-over (a
     * Customer's Orders, etc.) — Filament calls this a relation manager.
     * No-op by default; override to declare one or more.
     *
     * @return RelationManager[]
     */
    protected function relationManagers(): array
    {
        return [];
    }

    /**
     * Fetches $manager's relation for the record currently being viewed and
     * renders it as a compact table, reusing each declared Column's own
     * renderCell() — the same rendering Resource's main table() uses, just
     * against the related records instead of this resource's own. A
     * separate, targeted query (model::where(recordKey,key)->with([relation])),
     * not Resource::$with — that's for the *list* query; eager-loading a
     * to-many relation there for every row just to support this one
     * record's view panel would fetch it on every page load for nothing.
     */
    protected function renderRelationManager(RelationManager $manager): string
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $relation = $manager->getRelation();
        $modelClass = $this->model;
        $instance = new $modelClass();

        if (!method_exists($instance, $relation)) {
            return '';
        }

        $key = $this->viewing[$this->recordKey] ?? null;

        if ($key === null) {
            return '';
        }

        $record = $modelClass::where($this->recordKey, $key)->with([$relation])->first();
        $related = $record ? (array) ($record->toArray()[$relation] ?? []) : [];

        if ($manager->getLimit() !== null) {
            $related = array_slice($related, 0, $manager->getLimit());
        }

        $columns = $manager->getColumns();

        $html = '<div class="mt-8"><h3 class="text-xs font-extrabold uppercase text-azure-600">' . $escape($manager->getLabel()) . '</h3>';

        if ($related === []) {
            return $html . '<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No records.</p></div>';
        }

        $html .= '<div class="mt-2 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800"><table class="w-full text-sm"><thead><tr class="bg-slate-50 dark:bg-slate-900">';

        foreach ($columns as $column) {
            $html .= '<th class="px-3 py-2 text-left text-xs font-bold uppercase text-slate-500 dark:text-slate-400">' . $escape($column->getLabel()) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($related as $row) {
            $html .= '<tr class="border-t border-slate-200 dark:border-slate-800">';

            foreach ($columns as $column) {
                $html .= '<td class="px-3 py-2">' . $column->renderCell($row) . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></div>';

        return $html;
    }

    /**
     * Renders the per-row action buttons. Override to add/remove/replace actions
     * (e.g. a "Download" link instead of "View", or an extra custom action) —
     * this is the full markup for the cell, not a list that gets merged.
     */
    /**
     * @return Action[]
     */
    protected function rowActions(array $record): array
    {
        // Trashed records get their own action set - editing/viewing a
        // soft-deleted row in place doesn't make sense; restore it first.
        if ($this->softDeletes && $this->showTrash) {
            return [
                Action::make('restore')->label('Restore')->can('update')->action('restoreOne'),
                Action::make('forceDelete')->label('Delete permanently')->can('delete')
                    ->confirm('Permanently delete this record? This cannot be undone.')->action('forceDeleteOne'),
            ];
        }

        return [
            Action::make('view')->label('View')->can('view')->action('openView'),
            Action::make('edit')->label('Edit')->can('update')
                ->visible(fn () => $this->isCreatable())->action('openEdit'),
            Action::make('delete')->label('Delete')->color('danger')->can('delete')
                ->confirm($this->softDeletes ? 'Move this record to trash?' : 'Delete this record? This cannot be undone.')
                ->action('deleteOne'),
        ];
    }

    /**
     * Renders the bulk-selection toolbar actions (next to the "N selected" label).
     * Override to add custom bulk actions alongside or instead of bulk delete.
     *
     * @return Action[]
     */
    protected function bulkActions(): array
    {
        if ($this->softDeletes && $this->showTrash) {
            return [
                Action::make('restore')->label('Restore')->can('update')->action('bulkRestore'),
                Action::make('forceDelete')->label('Delete permanently')->color('danger')->can('delete')
                    ->confirm('Permanently delete the selected record(s)? This cannot be undone.')->action('bulkForceDelete'),
            ];
        }

        return [
            Action::make('delete')->label('Delete')->color('danger')->can('deleteAny')
                ->confirm(fn ($count) => ($this->softDeletes ? 'Move ' : 'Delete ') . $count
                    . ' selected record(s)' . ($this->softDeletes ? ' to trash?' : '? This cannot be undone.'))
                ->action('bulkDelete'),
        ];
    }

    /**
     * Extra buttons next to the built-in "+ New" header button (which stays
     * hardcoded since it's tightly coupled to isCreatable()/openCreate) -
     * e.g. an "Export" action. Empty by default.
     *
     * @return Action[]
     */
    protected function headerActions(): array
    {
        return [];
    }

    protected function renderRowActions(string $key, array $record): string
    {
        $html = '';

        foreach ($this->rowActions($record) as $action) {
            if (!$action->isVisible($record)) {
                continue;
            }

            if ($action->getAbility() !== null && !$this->can($action->getAbility(), $record)) {
                continue;
            }

            $html .= $action->render($key, $record, 'row') . ' ';
        }

        return trim($html);
    }

    protected function renderBulkActions(int $count): string
    {
        $html = '';

        foreach ($this->bulkActions() as $action) {
            if ($action->getAbility() !== null && !$this->can($action->getAbility(), [])) {
                continue;
            }

            $html .= $action->render(null, [], 'compact', $count) . ' ';
        }

        return trim($html);
    }

    protected function renderHeaderActions(): string
    {
        $html = '';

        foreach ($this->headerActions() as $action) {
            if ($action->getAbility() !== null && !$this->can($action->getAbility(), [])) {
                continue;
            }

            $html .= $action->withoutRecordKey()->render(null, [], 'row') . ' ';
        }

        return trim($html);
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

        // RolePolicy needs to know which resource it's guarding (for the
        // optional per-resource override in config('permissions.resources'))
        // - every other policy is a plain zero-arg instantiation.
        $this->resolvedPolicy = match (true) {
            $class === null => null,
            $class === RolePolicy::class => new RolePolicy(static::class),
            default => new $class(),
        };

        return $this->resolvedPolicy;
    }

    /**
     * Resolution order: an explicit $policy override; else the conventional
     * App\Models\X -> App\Policies\XPolicy guess (mirrors Laravel/Filament);
     * else RolePolicy, *only* once config('permissions.roles') has actually
     * been defined - so a project that hasn't set up roles at all keeps the
     * original "no policy = every ability allowed" default rather than
     * suddenly denying everything once this exists. Once a permissions
     * config is defined, it becomes the default for every resource without
     * each one needing its own bespoke Policy class (and the maintenance
     * risk of one being left in place - or removed - after the fact).
     */
    protected function resolvePolicyClass(): ?string
    {
        if ($this->policy !== null) {
            return class_exists($this->policy) ? $this->policy : null;
        }

        $guess = preg_replace('/\\\\Models\\\\/', '\\Policies\\', $this->model) . 'Policy';

        if (class_exists($guess)) {
            return $guess;
        }

        if ((array) config('permissions.roles', []) !== []) {
            return RolePolicy::class;
        }

        return null;
    }

    protected function currentUser(): mixed
    {
        return Auth::user();
    }
}
