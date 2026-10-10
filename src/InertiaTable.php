<?php

declare(strict_types=1);

namespace Digit7s\InertiaTable;

use Digit7s\InertiaTable\Exports\CsvExport;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

abstract class InertiaTable
{
    protected Request $request;

    protected ?\Closure $rowLinkCallback = null;

    protected ?array $perPageOptions = [15, 30, 50, 100];

    protected ?int $defaultPerPage = 15;

    protected string $primaryKey = 'id';

    protected ?string $defaultGroup = null;

    protected string $defaultGroupDir = 'asc';

    protected bool $paginated = true;

    /** @var array<int, string> Query columns injected only to support eager loading. */
    protected array $automaticallySelectedRelationshipColumns = [];

    /** @var array<int, string> */
    private array $automaticallySelectedSortColumns = [];

    /** @var array<string, bool> */
    private array $validatedRelationshipFields = [];

    /**
     * @var string|null The model class name that this table represents.
     */
    protected ?string $model = null;

    /**
     * @var string|null The resource name for identifying the model.
     */
    protected ?string $resource = null;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? request();
    }

    /**
     * Set the resource identifier for this table.
     */
    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    public function primaryKey(string $key): static
    {
        $this->primaryKey = $key;

        return $this;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Maximum number of records materialized for one bulk action.
     * Tables that intentionally support larger bulk operations may override this value.
     */
    public function maxBulkActionRecords(): int
    {
        return 10000;
    }

    abstract public function query(): Builder;

    /**
     * @return Column[]
     */
    abstract public function columns(): array;

    /**
     * @return Filter[]
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * @return Action[]
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return BulkAction[]
     */
    public function bulkActions(): array
    {
        return [];
    }

    /**
     * @return CsvExport[]
     */
    public function exports(): array
    {
        return [];
    }

    public function rowLink(\Closure $callback): static
    {
        $this->rowLinkCallback = $callback;

        return $this;
    }

    public function paginated(bool $paginated = true): static
    {
        $this->paginated = $paginated;

        return $this;
    }

    public function toInertia(): array
    {
        $tableState = $this->resolveTableState();
        $columns = collect($this->columns());
        $valueColumns = $columns->filter(function (Column $column): bool {
            $config = $column->toArray();

            return $config['visible'] || $config['toggleable'];
        });
        $query = $this->query();
        $automaticRelationships = $this->eagerLoadColumnRelationships($query, $valueColumns->all());
        $automaticSelectedColumns = $this->automaticallySelectedRelationshipColumns;
        $this->applySearch($query);
        $this->applyGrouping($query);
        $this->applySorting($query);
        $this->applyFiltering($query);

        if ($this->paginated) {
            $perPage = (int) $this->request->query('perPage', $this->defaultPerPage);
            if (! in_array($perPage, $this->perPageOptions ?? [15, 30, 50, 100])) {
                $perPage = $this->defaultPerPage;
            }

            $paginator = $query->paginate($perPage)
                ->withQueryString();

            $collection = collect($paginator->items());
            $meta = [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'table_class' => encrypt(static::class),
            ];

            $exports = $this->exports();
            if (count($exports) > 0) {
                $meta['exports'] = collect($exports)->map(function ($export) {
                    $array = $export->toArray();
                    $array['url'] = route('inertia-table.export');

                    return $array;
                })->toArray();
            }

            $links = $paginator->linkCollection()->toArray();
        } else {
            $collection = $query->get();
            $meta = [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'path' => $this->request->url(),
                'per_page' => $collection->count(),
                'to' => $collection->count(),
                'total' => $collection->count(),
                'table_class' => encrypt(static::class),
            ];

            $exports = $this->exports();
            if (count($exports) > 0) {
                $meta['exports'] = collect($exports)->map(function ($export) {
                    $array = $export->toArray();
                    $array['url'] = route('inertia-table.export');

                    return $array;
                })->toArray();
            }

            $links = [];
            $perPage = $collection->count();
        }

        $group = $this->request->query('group', $this->defaultGroup);
        $groupDir = $this->request->query('group_dir', $this->defaultGroupDir);

        $groupColumn = $group ? collect($this->columns())->first(fn (Column $column) => $column->key === $group && $column->isGroupable()) : null;

        $items = $collection->map(function ($item) use ($groupColumn, $valueColumns, $automaticRelationships, $automaticSelectedColumns) {
            if ($item instanceof Model) {
                foreach ($this->automaticallySelectedSortColumns as $selectedColumn) {
                    $item->offsetUnset($selectedColumn);
                }
            }

            $data = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);

            foreach ($automaticSelectedColumns as $selectedColumn) {
                unset($data[$selectedColumn]);
            }

            foreach ($this->automaticallySelectedSortColumns as $selectedColumn) {
                unset($data[$selectedColumn]);
            }

            if (is_object($item) && method_exists($item, 'toArray')) {
                foreach ($automaticRelationships as $relationship) {
                    $this->forgetSerializedPath($data, explode('.', $relationship));
                }
            }

            $columnValues = [];
            foreach ($valueColumns as $column) {
                if (str_contains($column->key, '.')) {
                    $columnValues[$column->key] = $this->resolveColumnValue($item, $column->key);
                }
            }

            if ($columnValues !== []) {
                $data['_column_values'] = $columnValues;
            }

            $id = null;
            if (is_object($item) && method_exists($item, 'getKey')) {
                $data['id'] = $item->getKey();
            } elseif (is_object($item) && isset($item->id)) {
                $data['id'] = $item->id;
            } elseif (is_array($item) && isset($item['id'])) {
                $data['id'] = $item['id'];
            } else {
                $id = is_array($item) ? ($item[$this->primaryKey] ?? null) : ($item->{$this->primaryKey} ?? null);
            }

            if ($id !== null) {
                $data['id'] = $id;
            }

            if ($this->rowLinkCallback) {
                $data['_row_link'] = call_user_func($this->rowLinkCallback, $item);
            }

            if ($groupColumn) {
                $groupValue = $groupColumn->getGroupValue($item);
                if ($groupValue !== null) {
                    $data['_group_value'] = $groupValue;
                }
            }

            $actions = $this->actions();
            if (! empty($actions)) {
                $data['_actions'] = collect($actions)->map(function (Action $action) use ($item) {
                    return $action->toArray($item);
                })->toArray();
            }

            return $data;
        })->toArray();

        return [
            'data' => $items,
            'links' => $links,
            'meta' => $meta,
            'paginated' => $this->paginated,
            'columns' => collect($this->columns())->map->toArray()->toArray(),
            'groups' => collect($this->columns())->filter->isGroupable()->map->toArray()->toArray(),
            'bulk_actions' => collect($this->bulkActions())->map->toArray()->toArray(),
            'filters' => collect($this->filters())->map(function (Filter $filter) use ($tableState) {
                return $filter->toArray(
                    $tableState['filter'][$filter->key] ?? null
                );
            })->toArray(),
            'query' => [
                'search' => $tableState['search'],
                'sort' => $this->request->query('sort', ''),
                'dir' => $this->request->query('dir', 'asc'),
                'group' => $group,
                'group_dir' => $groupDir,
                'filter' => $tableState['filter'],
                'perPage' => $perPage,
            ],
            'per_page_options' => $this->perPageOptions,
            'default_per_page' => $this->defaultPerPage,
        ];
    }

    /**
     * Eager-load only valid relationship prefixes used by dotted column keys.
     * Existing query eager loads and their constraints are retained.
     *
     * @return array<int, string> Automatically added relationship paths.
     */
    public function eagerLoadColumnRelationships(Builder $query, ?array $columns = null): array
    {
        $this->automaticallySelectedRelationshipColumns = [];
        $columns ??= $this->columns();
        $existingLoads = array_keys($query->getEagerLoads());
        $paths = $this->columnRelationshipPaths($query->getModel(), $columns);
        $automaticLoads = array_values(array_filter($paths, function (string $path) use ($existingLoads): bool {
            foreach ($existingLoads as $existingLoad) {
                if ($path === $existingLoad || str_starts_with($existingLoad, $path.'.')) {
                    return false;
                }
            }

            return true;
        }));

        if ($automaticLoads !== []) {
            $query->with($automaticLoads);
        }

        $this->automaticallySelectedRelationshipColumns = $this->ensureSelectedRelationshipKeys($query, $paths);

        return $automaticLoads;
    }

    /** Resolve a dotted column key without triggering an unloaded relationship. */
    public function resolveColumnValue(mixed $item, string $key): mixed
    {
        if (! str_contains($key, '.')) {
            return data_get($item, $key);
        }

        return $this->resolvePathValue($item, explode('.', $key));
    }

    /** @return array<int, string> */
    private function columnRelationshipPaths(Model $model, array $columns): array
    {
        $paths = [];

        foreach ($columns as $column) {
            $segments = explode('.', $column->key);
            if (count($segments) < 2) {
                continue;
            }

            $relatedModel = $model;
            $path = [];
            $relationshipSegments = array_slice($segments, 0, -1);

            foreach ($relationshipSegments as $index => $segment) {
                if ($relatedModel->hasAttribute($segment) || ! method_exists($relatedModel, $segment)) {
                    break;
                }

                try {
                    $relation = $relatedModel->{$segment}();
                } catch (\Throwable) {
                    break;
                }

                if (! $relation instanceof Relation) {
                    break;
                }

                $path[] = $segment;
                $paths[] = implode('.', $path);

                // MorphTo's concrete related type depends on each row. It can be
                // eager-loaded directly, but deeper paths need app-owned morphWith().
                if ($relation instanceof MorphTo && $index < count($relationshipSegments) - 1) {
                    break;
                }

                $relatedModel = $relation->getRelated();
            }
        }

        return array_values(array_unique($paths));
    }

    /** @param array<int, string> $paths @return array<int, string> */
    private function ensureSelectedRelationshipKeys(Builder $query, array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $selected = $query->getQuery()->columns;
        if ($selected === null || $selected === []) {
            return [];
        }

        $model = $query->getModel();
        $required = [$model->getKeyName()];

        foreach ($paths as $path) {
            $rootRelationship = explode('.', $path, 2)[0];
            try {
                $relation = $model->{$rootRelationship}();
            } catch (\Throwable) {
                continue;
            }

            if ($relation instanceof MorphTo) {
                $required[] = $relation->getForeignKeyName();
                $required[] = $relation->getMorphType();
            } elseif ($relation instanceof BelongsTo) {
                $required[] = $relation->getForeignKeyName();
            } elseif (method_exists($relation, 'getLocalKeyName')) {
                $required[] = $relation->getLocalKeyName();
            } elseif (method_exists($relation, 'getParentKeyName')) {
                $required[] = $relation->getParentKeyName();
            }
        }

        $selectedNames = [];
        $hasWildcard = false;
        foreach ($selected as $selectedColumn) {
            if (! is_string($selectedColumn)) {
                continue;
            }

            $selectedColumn = trim(explode(' as ', strtolower($selectedColumn), 2)[0], '`" ');
            $name = str_contains($selectedColumn, '.') ? substr($selectedColumn, strrpos($selectedColumn, '.') + 1) : $selectedColumn;
            $selectedNames[] = $name;
            $hasWildcard = $hasWildcard || $name === '*';
        }

        if ($hasWildcard) {
            return [];
        }

        $added = [];
        foreach (array_unique($required) as $column) {
            if (in_array(strtolower($column), $selectedNames, true)) {
                continue;
            }

            $query->addSelect($model->qualifyColumn($column));
            if ($column !== $model->getKeyName()) {
                $added[] = $column;
            }
        }

        return $added;
    }

    private function resolvePathValue(mixed $value, array $segments): mixed
    {
        if ($segments === []) {
            return $this->normalizeColumnValue($value);
        }

        if ($value instanceof Collection || is_array($value) && array_is_list($value)) {
            $resolved = [];

            foreach ($value as $item) {
                $itemValue = $this->resolvePathValue($item, $segments);
                if (is_array($itemValue) && array_is_list($itemValue)) {
                    array_push($resolved, ...$itemValue);
                } else {
                    $resolved[] = $itemValue;
                }
            }

            return $resolved;
        }

        $segment = array_shift($segments);

        if ($value instanceof Model) {
            if ($value->hasAttribute($segment)) {
                $nextValue = $value->getAttribute($segment);
            } elseif ($value->relationLoaded($segment)) {
                $nextValue = $value->getRelation($segment);
            } elseif ($this->isRelationshipMethod($value, $segment)) {
                // Never fall through to Eloquent's lazy relationship resolver.
                return null;
            } else {
                $nextValue = $value->getAttribute($segment);
            }
        } elseif (is_array($value)) {
            $nextValue = $value[$segment] ?? null;
        } else {
            $nextValue = data_get($value, $segment);
        }

        return $this->resolvePathValue($nextValue, $segments);
    }

    private function isRelationshipMethod(Model $model, string $name): bool
    {
        if (! method_exists($model, $name)) {
            return false;
        }

        try {
            return $model->{$name}() instanceof Relation;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeColumnValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\\TH:i:s.uP');
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if ($value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }

        return $value;
    }

    private function forgetSerializedPath(array &$data, array $segments): void
    {
        if ($segments === []) {
            return;
        }

        $key = Str::snake(array_shift($segments));
        if (! array_key_exists($key, $data)) {
            return;
        }

        if ($segments === []) {
            unset($data[$key]);

            return;
        }

        if (is_array($data[$key])) {
            $this->forgetSerializedPath($data[$key], $segments);
        }
    }

    public function applySearch(Builder $query): void
    {
        $search = $this->resolveTableState()['search'];

        if ($search === '') {
            return;
        }

        $searchableColumns = collect($this->columns())
            ->filter->isSearchable()
            ->flatMap->getSearchableColumns();

        if ($searchableColumns->isEmpty()) {
            return;
        }

        $query->where(function ($query) use ($search, $searchableColumns) {
            foreach ($searchableColumns as $column) {
                if (str_contains($column, '.')) {
                    $relation = str($column)->beforeLast('.')->toString();
                    $field = str($column)->afterLast('.')->toString();

                    $query->orWhereHas($relation, function (Builder $query) use ($field, $search) {
                        $this->whereCaseInsensitiveLike($query, $field, $search);
                    });
                } else {
                    $this->whereCaseInsensitiveLike($query, $column, $search, true);
                }
            }
        });
    }

    private function whereCaseInsensitiveLike(Builder $query, string $field, string $value, bool $or = false): void
    {
        $wrappedField = $query->getQuery()->getGrammar()->wrap($field);
        $pattern = '%'.mb_strtolower($value).'%';

        if ($or) {
            $query->orWhereRaw('LOWER('.$wrappedField.') LIKE ?', [$pattern]);
        } else {
            $query->whereRaw('LOWER('.$wrappedField.') LIKE ?', [$pattern]);
        }
    }

    public function applyGrouping(Builder $query): void
    {
        $group = $this->request->query('group', $this->defaultGroup);
        $dir = $this->request->query('group_dir', $this->defaultGroupDir);

        if (! $group) {
            return;
        }

        $column = collect($this->columns())
            ->first(fn (Column $column) => $column->key === $group && $column->isGroupable());

        if (! $column) {
            return;
        }

        $query->orderBy($column->getGroupColumn(), $dir);
    }

    public function applySorting(Builder $query): void
    {
        $this->automaticallySelectedSortColumns = [];
        $sort = $this->request->query('sort');
        $dir = $this->request->query('dir', 'asc');

        if ($sort === null || $sort === '') {
            return;
        }

        $column = collect($this->columns())
            ->first(fn (Column $column) => $column->key === $sort && $column->isSortable());

        if (! $column || ! in_array($dir, ['asc', 'desc'], true)) {
            throw ValidationException::withMessages([
                'sort' => 'The requested sort is invalid for this table.',
            ]);
        }

        $direction = $dir;
        if (! str_contains($sort, '.')) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $sort)) {
                throw ValidationException::withMessages([
                    'sort' => 'The requested sort is invalid for this table.',
                ]);
            }

            $query->orderBy($query->getModel()->qualifyColumn($sort), $direction);
        } else {
            [$relationships, $field] = $this->resolveRelationshipField($query->getModel(), $sort, true);
            $subquery = $this->relationshipSortSubquery($query, $query->getModel(), $relationships, $field);
            $sortAlias = '__it_sort_'.substr(hash('sha256', $sort), 0, 12);

            if ($query->getQuery()->columns === null) {
                $query->select($query->getModel()->qualifyColumn('*'));
            }

            $query->selectSub($subquery, $sortAlias);

            // PostgreSQL supports NULLS LAST directly on the selected alias. SQLite and MySQL
            // permit the alias in an ORDER BY expression, which keeps the correlated lookup
            // selected only once per row on those grammars too.
            $wrappedAlias = $query->getQuery()->getGrammar()->wrap($sortAlias);
            if ($query->getQuery()->getGrammar() instanceof PostgresGrammar) {
                $query->orderByRaw("{$wrappedAlias} {$direction} nulls last");
            } else {
                $query->orderByRaw("case when {$wrappedAlias} is null then 1 else 0 end asc");
                $query->orderBy($sortAlias, $direction);
            }
            $this->automaticallySelectedSortColumns[] = $sortAlias;
        }

        // Equal sort values must not drift between pages. Use the model key rather than an
        // unqualified `id`, which also works for scoped or joined application queries.
        $primaryKey = $query->getModel()->getQualifiedKeyName();
        if ($sort !== $query->getModel()->getKeyName() && $sort !== $primaryKey) {
            $query->orderBy($primaryKey, 'asc');
        }
    }

    public function applyFiltering(Builder $query): void
    {
        $requestFilters = $this->resolveTableState()['filter'];

        if (empty($requestFilters)) {
            return;
        }

        $availableFilters = collect($this->filters())->keyBy('key');

        foreach ($requestFilters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $filter = $availableFilters->get($key);
            if ($filter->hasQueryCallback()) {
                $filter->apply($query, $value);
            } elseif (str_contains($filter->key, '.')) {
                [$relationships, $field] = $this->resolveRelationshipField($query->getModel(), $filter->key, false);
                $query->whereHas(implode('.', $relationships), function (Builder $relatedQuery) use ($filter, $field, $value) {
                    $filter->applyValue($relatedQuery, $field, $value);
                });
            } else {
                $filter->apply($query, $value);
            }
        }
    }

    /**
     * Resolve a configured dotted field into relationship names and a safe leaf identifier.
     * MorphTo is intentionally excluded: its related table varies by row and needs an
     * application-defined strategy.
     *
     * @return array{0: array<int, string>, 1: string}
     */
    private function resolveRelationshipField(Model $model, string $key, bool $singularOnly): array
    {
        $segments = explode('.', $key);
        $field = array_pop($segments);

        if (! is_string($field) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) || $segments === []) {
            throw ValidationException::withMessages([
                $singularOnly ? 'sort' : 'filter' => 'The configured relationship field is invalid.',
            ]);
        }

        $current = $model;
        foreach ($segments as $name) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) || ! method_exists($current, $name)) {
                throw ValidationException::withMessages([
                    $singularOnly ? 'sort' : 'filter' => 'The configured relationship field is invalid.',
                ]);
            }

            try {
                $relation = Relation::noConstraints(fn () => $current->{$name}());
            } catch (\Throwable) {
                $relation = null;
            }

            if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                throw ValidationException::withMessages([
                    $singularOnly ? 'sort' : 'filter' => 'This relationship field is not supported.',
                ]);
            }

            if ($singularOnly && ! ($relation instanceof BelongsTo || $relation instanceof HasOne || $relation instanceof MorphOne)) {
                throw ValidationException::withMessages([
                    'sort' => 'Sorting by collection or polymorphic target relationships is not supported.',
                ]);
            }

            $current = $relation->getRelated();
        }

        $connection = $current->getConnection();
        $cacheKey = implode('|', [
            $connection->getName(),
            $connection->getDatabaseName(),
            $current->getTable(),
            $field,
        ]);

        if (! array_key_exists($cacheKey, $this->validatedRelationshipFields)) {
            $this->validatedRelationshipFields[$cacheKey] = $connection
                ->getSchemaBuilder()
                ->hasColumn($current->getTable(), $field);
        }

        if (! $this->validatedRelationshipFields[$cacheKey]) {
            throw ValidationException::withMessages([
                $singularOnly ? 'sort' : 'filter' => 'The configured relationship field is invalid.',
            ]);
        }

        return [$segments, $field];
    }

    /** Build a scope-preserving scalar subquery for a verified singular relationship chain. */
    private function relationshipSortSubquery(Builder $parentQuery, Model $parent, array $relationships, string $field): Builder
    {
        $name = array_shift($relationships);
        $relation = Relation::noConstraints(fn () => $parent->{$name}());
        $relatedModel = $relation->getRelated();
        $relatedQuery = $relatedModel->newQuery();

        if ($relationships !== []) {
            $nestedSubquery = $this->relationshipSortSubquery($relatedQuery, $relatedModel, $relationships, $field);
            $selectedColumn = new Expression('('.$nestedSubquery->toSql().')');
        } else {
            $selectedColumn = $relatedModel->qualifyColumn($field);
        }

        $subquery = $relation->getRelationExistenceQuery($relatedQuery, $parentQuery, [$selectedColumn]);

        if ($relationships !== []) {
            $subquery->addBinding($nestedSubquery->getBindings(), 'select');
        }

        // Include constraints explicitly declared in the relationship method while keeping
        // model global scopes on the related query.
        $subquery->mergeConstraintsFrom($relation->getQuery());

        return $subquery->limit(1);
    }

    /**
     * Resolve the table state from either query parameters or the existing bulk-action
     * POST payload. Body values take precedence when both sources provide a value.
     *
     * @return array{search: string, filter: array<string, mixed>}
     */
    public function resolveTableState(): array
    {
        $search = $this->request->input('search', '');

        if (! is_string($search) || mb_strlen($search) > 500) {
            throw ValidationException::withMessages([
                'search' => 'The search value must be a string of at most 500 characters.',
            ]);
        }

        $filters = $this->request->input('filter', []);

        if ($this->request->has('filters')) {
            $serializedFilters = $this->request->input('filters');

            if (! is_string($serializedFilters) || strlen($serializedFilters) > 20000) {
                throw ValidationException::withMessages([
                    'filters' => 'The filters value must be a valid JSON object.',
                ]);
            }

            try {
                $decodedObject = json_decode($serializedFilters, false, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages([
                    'filters' => 'The filters value must be a valid JSON object.',
                ]);
            }

            if (! $decodedObject instanceof \stdClass) {
                throw ValidationException::withMessages([
                    'filters' => 'The filters value must be a JSON object keyed by filter name.',
                ]);
            }

            $decodedFilters = json_decode($serializedFilters, true, 32, JSON_THROW_ON_ERROR);

            if ($filters !== [] && $filters !== $decodedFilters) {
                throw ValidationException::withMessages([
                    'filters' => 'Submit filter state in only one format or provide matching values.',
                ]);
            }

            $filters = $decodedFilters;
        }

        if (! is_array($filters) || count($filters) > 50 || array_is_list($filters) && $filters !== []) {
            throw ValidationException::withMessages([
                'filter' => 'The filter state must be an object with at most 50 filters.',
            ]);
        }

        $availableFilters = collect($this->filters())->keyBy('key');

        foreach ($filters as $key => $value) {
            if (! is_string($key) || ! $availableFilters->has($key)) {
                throw ValidationException::withMessages([
                    "filter.{$key}" => 'This filter is not available for the table.',
                ]);
            }

            $filter = $availableFilters->get($key);

            if ($value === null || $value === '') {
                continue;
            }

            if ($filter->getType() === 'boolean') {
                if (! in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                    throw ValidationException::withMessages([
                        "filter.{$key}" => 'The boolean filter value is invalid.',
                    ]);
                }

                continue;
            }

            if ($filter->getType() === 'select' && $filter->getOptions() !== []) {
                if (! is_scalar($value) || ! array_key_exists((string) $value, $filter->getOptions())) {
                    throw ValidationException::withMessages([
                        "filter.{$key}" => 'The selected filter value is invalid.',
                    ]);
                }

                continue;
            }

            if (! $filter->hasQueryCallback() && ! is_scalar($value)) {
                throw ValidationException::withMessages([
                    "filter.{$key}" => 'The filter value must be a scalar value.',
                ]);
            }

            if (! $this->isSafeFilterValue($value)) {
                throw ValidationException::withMessages([
                    "filter.{$key}" => 'The filter value has an invalid type or size.',
                ]);
            }
        }

        return [
            'search' => $search,
            'filter' => $filters,
        ];
    }

    private function isSafeFilterValue(mixed $value, int $depth = 0): bool
    {
        if ($depth > 3) {
            return false;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= 500;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return true;
        }

        if (! is_array($value) || count($value) > 50) {
            return false;
        }

        foreach ($value as $nestedValue) {
            if (! $this->isSafeFilterValue($nestedValue, $depth + 1)) {
                return false;
            }
        }

        return true;
    }
}
