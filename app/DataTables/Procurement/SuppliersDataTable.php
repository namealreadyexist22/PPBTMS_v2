<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Models\Procurement\Supplier;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Suppliers, one page at a time: search (name, address or TIN), sorting and paging run in the database.
 * The search box and the status filter are the page's own (see the list's script).
 */
class SuppliersDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblSuppliers';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Every column but the raw ones is escaped by Yajra
        return (new EloquentDataTable($query))
            ->editColumn('address', fn (Supplier $s) => $s->address ?: '—')
            ->editColumn('tin', fn (Supplier $s) => $s->tin ?: '—')
            ->editColumn('contact', fn (Supplier $s) => $s->contact ?: '—')
            ->editColumn('philgeps_no', fn (Supplier $s) => $s->philgeps_no ?: '—')
            ->editColumn('is_active', fn (Supplier $s) => '<span class="badge ' . ($s->is_active ? 'bg-success">Active' : 'bg-secondary">Inactive') . '</span>')
            ->addColumn('action', fn (Supplier $s) => view('procurement.suppliers.extras.actions', ['supplier' => $s])->render())
            ->rawColumns(['is_active', 'action'])
            ->setRowClass(fn (Supplier $s) => $s->is_active ? '' : 'text-muted')
            ->setRowId('id');
    }

    public function query(Supplier $model): QueryBuilder
    {
        $status = request('status');

        return $model->newQuery()
            ->withCount('aoqSuppliers')
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false));
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            // The page's status filter goes with every request
            ->minifiedAjax(route('procurement.suppliers.index'), $this->filterFormScript('suppliers_filter'))
            ->orderBy(0, 'asc')
            ->parameters($this->listParameters('No suppliers yet.', 'No suppliers match the search.'));
    }

    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Supplier')->addClass('fw-semibold'),
            Column::make('address')->title('Address'),
            // Text, not numbers: DataTables would otherwise right-align digit-only values
            Column::make('tin')->title('TIN')->type('string')->addClass('text-nowrap'),
            Column::make('contact')->title('Contact')->type('string')->searchable(false),
            Column::make('philgeps_no')->title('PhilGEPS No.')->type('string')->searchable(false)->addClass('text-nowrap'),
            Column::make('aoq_suppliers_count')->title('On AOQs')->searchable(false)->addClass('text-center'),
            Column::make('is_active')->title('Status')->searchable(false)->addClass('text-center'),
            Column::computed('action')->title('')->addClass('text-end text-nowrap'),
        ];
    }

    protected function filename(): string
    {
        return 'Suppliers_' . date('YmdHis');
    }
}
