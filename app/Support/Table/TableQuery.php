<?php

namespace App\Support\Table;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Applies the search, sort, filter and pagination that the frontend
 * `DataTable` sends up as query-string state.
 *
 * Every column a caller exposes is whitelisted explicitly — request input
 * never reaches an ORDER BY or WHERE clause by name.
 *
 * @template TModel of Model
 */
final class TableQuery
{
    private const int MAX_PER_PAGE = 200;

    /** @var list<string> */
    private array $searchable = [];

    /** @var array<string, Closure|string> */
    private array $sortable = [];

    private ?string $defaultSort = null;

    private string $defaultDirection = 'asc';

    /** @var array<string, Closure|string> */
    private array $filterable = [];

    private int $defaultPerPage = 25;

    /**
     * @param  Builder<TModel>  $query
     */
    private function __construct(
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    /**
     * @template TFor of Model
     *
     * @param  Builder<TFor>  $query
     * @return self<TFor>
     */
    public static function for(Builder $query, ?Request $request = null): self
    {
        return new self($query, $request ?? request());
    }

    /**
     * Columns matched against the `search` parameter, OR'd together.
     *
     * @param  list<string>  $columns
     * @return self<TModel>
     */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * Columns the table may be ordered by.
     *
     * Pass a plain list for own columns, or a map of key => column name to
     * rename, or key => closure for anything needing a join or subquery.
     *
     * @param  list<string>|array<string, Closure|string>  $columns
     * @return self<TModel>
     */
    public function sortable(array $columns, ?string $default = null, string $direction = 'asc'): self
    {
        $this->sortable = self::keyByName($columns, 'sort');

        $this->defaultSort = $default;
        $this->defaultDirection = $direction === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    /**
     * Filters applied when their parameter is present and non-empty.
     *
     * Pass key => column for an equality match, or key => closure receiving
     * the builder and the value for anything else.
     *
     * @param  list<string>|array<string, Closure|string>  $filters
     * @return self<TModel>
     */
    public function filterable(array $filters): self
    {
        $this->filterable = self::keyByName($filters, 'filter');

        return $this;
    }

    /**
     * Normalise a list of column names, or a map of name => column/closure,
     * into a map keyed by the name the request uses.
     *
     * @param  array<array-key, Closure|string>  $definitions
     * @return array<string, Closure|string>
     *
     * @throws InvalidArgumentException
     */
    private static function keyByName(array $definitions, string $context): array
    {
        $keyed = [];

        foreach ($definitions as $key => $definition) {
            if (is_string($key)) {
                $keyed[$key] = $definition;

                continue;
            }

            if (! is_string($definition)) {
                throw new InvalidArgumentException(
                    "A {$context} defined by a closure needs a name the request can address it by: ".
                    "pass it as ['name' => fn (\$query, \$value) => ...]."
                );
            }

            $keyed[$definition] = $definition;
        }

        return $keyed;
    }

    /**
     * Restrict to rows whose `$column` falls inside the requested date range.
     *
     * @return self<TModel>
     */
    public function dateRange(string $column, string $fromParameter = 'from', string $toParameter = 'to'): self
    {
        $from = $this->parameter($fromParameter);
        $to = $this->parameter($toParameter);

        if ($from !== null) {
            $this->query->whereDate($column, '>=', $from);
        }

        if ($to !== null) {
            $this->query->whereDate($column, '<=', $to);
        }

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function perPage(int $perPage): self
    {
        $this->defaultPerPage = $perPage;

        return $this;
    }

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(): LengthAwarePaginator
    {
        $this->applySearch();
        $this->applyFilters();
        $this->applySort();

        $paginator = $this->query->paginate(
            perPage: $this->resolvedPerPage(),
            page: max(1, (int) ($this->parameter('page') ?? 1)),
        );

        // Read the page and rebuild the links from this instance's request
        // rather than the global one, so an explicitly injected request
        // paginates the same rows it filtered.
        return $paginator->appends(Arr::except($this->request->query(), 'page'));
    }

    /**
     * Total a column across everything the current filters match, ignoring
     * pagination — the figure someone came to a filtered list to see.
     *
     * Applies the same search, filters and date range as `paginate()`, so the
     * total always describes the rows on screen rather than the whole table.
     */
    public function sum(string $column): int
    {
        $this->applySearch();
        $this->applyFilters();

        return (int) $this->query->sum($column);
    }

    /**
     * The table state to hand back to the frontend so the UI can render the
     * active sort arrow, the search box contents and the selected filters.
     *
     * @return array{search: string|null, sort: string|null, direction: string, per_page: int, filters: array<string, string>}
     */
    public function state(): array
    {
        $filters = [];

        foreach (array_keys($this->filterable) as $key) {
            $value = $this->parameter($key);

            if ($value !== null) {
                $filters[$key] = $value;
            }
        }

        return [
            'search' => $this->parameter('search'),
            'sort' => $this->resolvedSort(),
            'direction' => $this->resolvedDirection(),
            'per_page' => $this->resolvedPerPage(),
            'filters' => $filters,
        ];
    }

    private function applySearch(): void
    {
        $term = $this->parameter('search');

        if ($term === null || $this->searchable === []) {
            return;
        }

        $this->query->where(function (Builder $query) use ($term): void {
            foreach ($this->searchable as $column) {
                $query->orWhereLike($column, "%{$term}%", caseSensitive: false);
            }
        });
    }

    private function applyFilters(): void
    {
        foreach ($this->filterable as $key => $filter) {
            $value = $this->parameter($key);

            if ($value === null) {
                continue;
            }

            if ($filter instanceof Closure) {
                $filter($this->query, $value);

                continue;
            }

            $this->query->where($filter, $value);
        }
    }

    private function applySort(): void
    {
        $sort = $this->resolvedSort();

        if ($sort === null) {
            return;
        }

        $column = $this->sortable[$sort];
        $direction = $this->resolvedDirection();

        if ($column instanceof Closure) {
            $column($this->query, $direction);

            return;
        }

        $this->query->orderBy($column, $direction);
    }

    private function resolvedSort(): ?string
    {
        $requested = $this->parameter('sort');

        if ($requested !== null && array_key_exists($requested, $this->sortable)) {
            return $requested;
        }

        return $this->defaultSort !== null && array_key_exists($this->defaultSort, $this->sortable)
            ? $this->defaultSort
            : null;
    }

    /**
     * @return 'asc'|'desc'
     */
    private function resolvedDirection(): string
    {
        $requested = $this->parameter('direction');

        if ($requested !== null) {
            return strtolower($requested) === 'desc' ? 'desc' : 'asc';
        }

        return $this->defaultDirection === 'desc' ? 'desc' : 'asc';
    }

    private function resolvedPerPage(): int
    {
        $requested = (int) ($this->parameter('per_page') ?? 0);

        if ($requested < 1) {
            return $this->defaultPerPage;
        }

        return min($requested, self::MAX_PER_PAGE);
    }

    /**
     * Read a request parameter, treating blank strings as absent so that
     * clearing a filter in the UI does not become `where x = ''`.
     */
    private function parameter(string $key): ?string
    {
        $value = $this->request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
