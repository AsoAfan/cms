<?php

namespace App\Http\Concerns;

use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Wires an index controller to the frontend `DataTable`.
 *
 * A typical index action reads:
 *
 *     $table = $this->table(Product::query())
 *         ->searchable(['name', 'sku'])
 *         ->sortable(['name', 'created_at'], default: 'name')
 *         ->filterable(['category_id']);
 *
 *     return Inertia::render('products/index', $this->tableProps($table));
 */
trait InteractsWithTables
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TableQuery<TModel>
     */
    protected function table(Builder $query): TableQuery
    {
        return TableQuery::for($query, request());
    }

    /**
     * The paginated rows plus the table state `DataTable` needs to render its
     * current search term, sort arrow and selected filters.
     *
     * @param  TableQuery<covariant Model>  $table
     * @return array{rows: mixed, table: array{search: string|null, sort: string|null, direction: string, per_page: int, filters: array<string, string>}}
     */
    protected function tableProps(TableQuery $table): array
    {
        return [
            'rows' => $table->paginate(),
            'table' => $table->state(),
        ];
    }
}
