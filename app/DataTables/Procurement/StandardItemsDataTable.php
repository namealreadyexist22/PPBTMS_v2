<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Models\Procurement\Item;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Standard items, one page at a time: search (code or name), sorting and paging run in the database.
 * The search box and the category filter are the page's own (see the list's script).
 */
class StandardItemsDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblItems';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Every column but the raw ones is escaped by Yajra
        return (new EloquentDataTable($query))
            ->addColumn('category', fn (Item $item) => $item->category?->name ?? '—')
            ->addColumn('unit', fn (Item $item) => $item->unit?->name)
            ->editColumn('standard_unit_cost', fn (Item $item) => $item->isStandard() ? '₱' . number_format((float) $item->standard_unit_cost, 2) : '—')
            ->addColumn('specs', fn (Item $item) => view('procurement.items.extras.specs_cell', ['item' => $item])->render())
            ->editColumn('is_active', fn (Item $item) => '<span class="badge ' . ($item->is_active ? 'bg-success">Active' : 'bg-secondary">Inactive') . '</span>')
            ->addColumn('action', fn (Item $item) => view('procurement.items.extras.actions', ['item' => $item])->render())
            ->rawColumns(['specs', 'is_active', 'action'])
            ->setRowClass(fn (Item $item) => $item->is_active ? '' : 'text-muted')
            ->setRowId('id');
    }

    public function query(Item $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['unit', 'category'])
            ->withCount('ppmpItems')
            ->when(request()->filled('category'), fn ($q) => $q->where('item_category_id', request()->integer('category')));
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            // The page's category filter goes with every request
            ->minifiedAjax(route('procurement.items.index'), $this->filterFormScript('items_filter'))
            ->orderBy(0, 'asc')
            ->parameters($this->listParameters('No standard items yet.', 'No items match the search.'));
    }

    public function getColumns(): array
    {
        return [
            Column::make('code')->title('Code')->addClass('fw-semibold text-nowrap'),
            Column::make('name')->title('Item'),
            Column::computed('category')->title('Category'),
            Column::computed('unit')->title('Unit'),
            Column::make('standard_unit_cost')->title('Standard Cost')->searchable(false)->addClass('text-end text-nowrap fw-semibold'),
            Column::computed('specs')->title('Specifications (TWG)'),
            Column::make('ppmp_items_count')->title('Used by')->searchable(false)->addClass('text-center'),
            Column::make('is_active')->title('Status')->searchable(false)->addClass('text-center'),
            Column::computed('action')->title('')->addClass('text-end text-nowrap'),
        ];
    }

    protected function filename(): string
    {
        return 'Standard_Items_' . date('YmdHis');
    }
}
