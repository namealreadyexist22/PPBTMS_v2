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
            ->addColumn('office', fn ($row) => $row->office?->shortName())
            // Search also finds the office by acronym, name or number
            ->filterColumn('office', fn ($query, $keyword) => $query->whereHas('office', fn ($o) => $o
                ->where('acronym', 'like', "%{$keyword}%")->orWhere('name', 'like', "%{$keyword}%")->orWhere('code', 'like', "%{$keyword}%")))
            ->editColumn('region', fn ($row) => $row->region->short())
            ->editColumn('type', fn ($row) => $row->type->label())
            ->editColumn('total_budget', fn ($row) => number_format($row->total_budget, 2))
            ->editColumn('status', fn ($row) => view('procurement.ppmp.extras.ppmp_status', ['ppmp' => $row])->render())
            ->rawColumns(['status'])
            // The whole row opens the PPMP (see the list's script)
            ->setRowAttr([
                'data-url' => fn ($row) => route('procurement.ppmp.show', $row),
                'class'    => 'ppmp-row',
                'role'     => 'link',
                'tabindex' => '0',
                'title'    => fn ($row) => "Open PPMP {$row->ppmp_no}",
            ])
            ->setRowId('id');
    }

    public function query(Ppmp $model): QueryBuilder
    {
        // Own office, sections of a division you head, or everything with "PPMP View All".
        return $model->newQuery()
            ->with('office')
            ->visibleTo(auth()->user())
            ->latest('id');
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
            Column::make('region')->title('Region'),
            Column::make('office')->title('Office')->orderable(false)->searchable(true),
            Column::make('type')->title('Type'),
            Column::make('version')->title('Ver.')->addClass('text-center'),
            Column::make('total_budget')->title('Total Budget')->addClass('text-end'),
            Column::make('status')->title('Status')->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'PPMP_' . date('YmdHis');
    }
}