<?php

namespace App\Core\DataTables;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\Models\Procurement\Office;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class OfficesDataTable extends DataTable
{
    use HasModernDataTable;

    protected string $tableId = 'tblOffices';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('parent', fn ($row) => $row->parent?->shortName() ?? '<span class="text-muted">— Top level —</span>')
            ->editColumn('name', fn ($row) => e($row->name) . ($row->is_consolidating ? ' <span class="badge bg-info-subtle text-info border" title="Consolidates PPMPs">Division PPMP</span>' : ''))
            ->addColumn('head', fn ($row) => $row->head?->fullname ?? '<span class="text-muted">Not set</span>')
            ->addColumn('users_count', fn ($row) => $row->users_count)
            ->editColumn('is_active', fn ($row) => view('BackEnd.auth.extras.user_is_activated', ['is_activated' => $row->is_active])->render())
            ->addColumn('action', fn ($row) => view('BackEnd.offices.extras.office_action', ['office' => $row])->render())
            ->rawColumns(['name', 'parent', 'head', 'is_active', 'action'])
            ->setRowId('id');
    }

    public function query(Office $model): QueryBuilder
    {
        return $model->newQuery()->with(['parent', 'head'])->withCount('users');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder());
    }

    public function getColumns(): array
    {
        return [
            Column::make('code')->title('Office No.'),
            Column::make('acronym')->title('Acronym'),
            Column::make('name')->title('Office Name'),
            Column::computed('parent')->title('Under'),
            Column::computed('head')->title('Head'),
            Column::computed('users_count')->title('Users')->addClass('text-center'),
            Column::make('is_active')->title('Status')->addClass('text-center'),
            Column::computed('action')->title('')->addClass('text-center')->width(50),
        ];
    }

    protected function filename(): string
    {
        return 'Offices_' . date('YmdHis');
    }
}
