<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\Models\Procurement\Ppmp;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PpmpDataTable extends DataTable
{
    use HasModernDataTable;

    protected string $tableId = 'tblPpmp';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('office', fn ($row) => $row->office?->code)
            ->editColumn('type', fn ($row) => $row->type->label())
            ->editColumn('total_budget', fn ($row) => number_format($row->total_budget, 2))
            ->editColumn('status', fn ($row) => view('procurement.ppmp.extras.ppmp_status', ['ppmp' => $row])->render())
            ->addColumn('action', fn ($row) => view('procurement.ppmp.extras.ppmp_action', ['ppmp' => $row])->render())
            ->rawColumns(['status', 'action'])
            ->setRowId('id');
    }

    public function query(Ppmp $model): QueryBuilder
    {
        $user = auth()->user();
        $query = $model->newQuery()->with('office')->latest('id');

        // Super Admin sees everything; everyone else sees their own office,
        // plus offices they head (for approving).
        if (! $user->hasRole('Super Admin')) {
            $query->whereHas('office', fn ($q) => $q
                ->where('id', $user->office_id)
                ->orWhere('head_user_id', $user->id));
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('ppmp_no')->title('PPMP No.'),
            Column::make('fiscal_year')->title('FY'),
            Column::computed('office')->title('Office'),
            Column::make('type')->title('Type'),
            Column::make('version')->title('Ver.')->addClass('text-center'),
            Column::make('total_budget')->title('Total Budget')->addClass('text-end'),
            Column::make('status')->title('Status')->addClass('text-center'),
            Column::computed('action')->title('')->addClass('text-center')->width(50),
        ];
    }

    protected function filename(): string
    {
        return 'PPMP_' . date('YmdHis');
    }
}