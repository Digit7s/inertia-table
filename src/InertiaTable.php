<?php

declare(strict_types=1);

namespace Digit7s\InertiaTable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
     * @return \Digit7s\InertiaTable\Exports\CsvExport[]
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
        $query = $this->query();
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

        $items = $collection->map(function ($item) use ($groupColumn) {
            $data = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);

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
            'filters' => collect($this->filters())->map(function (Filter $filter) {
                return $filter->toArray(
                    $this->request->query("filter.{$filter->key}")
                );
            })->toArray(),
            'query' => [
                'search' => $this->request->query('search', ''),
                'sort' => $this->request->query('sort', ''),
                'dir' => $this->request->query('dir', 'asc'),
                'group' => $group,
                'group_dir' => $groupDir,
                'filter' => $this->request->query('filter', []),
                'perPage' => $perPage,
            ],
            'per_page_options' => $this->perPageOptions,
            'default_per_page' => $this->defaultPerPage,
        ];
    }

    public function applySearch(Builder $query): void
    {
        $search = $this->request->query('search');

        if (! $search) {
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
                        $query->where($field, 'like', "%{$search}%");
                    });
                } else {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            }
        });
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
        $sort = $this->request->query('sort');
        $dir = $this->request->query('dir', 'asc');

        if (! $sort) {
            return;
        }

        $isSortable = collect($this->columns())
            ->filter->isSortable()
            ->contains(fn ($column) => $column->key === $sort);

        if ($isSortable) {
            $query->orderBy($sort, $dir === 'desc' ? 'desc' : 'asc');
        }
    }

    public function applyFiltering(Builder $query): void
    {
        $requestFilters = $this->request->query('filter');

        if (! is_array($requestFilters) || empty($requestFilters)) {
            return;
        }

        $availableFilters = collect($this->filters())->keyBy('key');

        foreach ($requestFilters as $key => $value) {
            if (! $availableFilters->has($key) || $value === null || $value === '') {
                continue;
            }

            $filter = $availableFilters->get($key);
            $filter->apply($query, $value);
        }
    }
}
