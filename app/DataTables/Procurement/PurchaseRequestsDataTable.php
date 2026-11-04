<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Models\Procurement\PurchaseRequest;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * PRs or JRs (the tab) the user may see, one page at a time, newest first (drafts on top).
 * Narrowed by the page's search (number or purpose), status (default: all but superseded) and year.
 */
class PurchaseRequestsDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblRequests';

    public static function kind(): RequestKind
    {
        return RequestKind::tryFrom((string) request('kind')) ?? RequestKind::Pr;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Every column but the raw ones is escaped by Yajra
        return (new EloquentDataTable($query))
            ->editColumn('request_no', fn (PurchaseRequest $r) => view('procurement.requests.extras.list_number', ['row' => $r])->render())
            ->addColumn('date', fn (PurchaseRequest $r) => ($r->submitted_at ?? $r->created_at)->format('M d, Y'))
            ->addColumn('charge_to', fn (PurchaseRequest $r) => $r->pap?->code)
            ->addColumn('items_list', fn (PurchaseRequest $r) => view('procurement.partials.items_cell', ['lines' => $r->items])->render())
            ->editColumn('total_amount', fn (PurchaseRequest $r) => number_format((float) $r->total_amount, 2))
            ->editColumn('status', fn (PurchaseRequest $r) => view('procurement.requests.extras.list_status', ['row' => $r])->render())
            ->addColumn('action', fn (PurchaseRequest $r) => view('procurement.requests.extras.list_action', ['row' => $r])->render())
            ->filter(function ($query) {
                if (($search = $this->searchTerm()) !== '') {
                    $query->where(fn ($w) => $w->where('request_no', 'like', "%{$search}%")->orWhere('purpose', 'like', "%{$search}%"));
                }
            })
            // The tab counts follow the year
            ->with('counts', $this->visible()->where('status', '!=', RequestStatus::Superseded)
                ->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind'))
            ->rawColumns(['request_no', 'items_list', 'status', 'action'])
            ->setRowId('id');
    }

    /** What the user may see, in the picked year. */
    protected function visible(): QueryBuilder
    {
        $year = static::selectedYear();

        return PurchaseRequest::visibleTo(auth()->user())->when($year, fn ($q) => $q->ofYear($year));
    }

    public function query(PurchaseRequest $model): QueryBuilder
    {
        $status = RequestStatus::tryFrom((string) request('status'));

        return $this->visible()
            ->with(['pap', 'items'])
            ->where('kind', static::kind())
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when(! $status, fn ($q) => $q->where('status', '!=', RequestStatus::Superseded))
            ->orderByRaw('request_no is null desc')
            ->orderByDesc('series_year')->orderByDesc('series')->orderByDesc('revision')->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            ->minifiedAjax(route('procurement.requests.index'), $this->filterFormScript('requests_filter'))
            // Newest first (drafts on top), as the query orders them
            ->parameters($this->listParameters('No requests yet.', 'No requests match the search.', ordering: false));
    }

    public function getColumns(): array
    {
        $jr = static::kind() === RequestKind::Jr;

        return [
            Column::make('request_no')->title($jr ? 'J.R. No.' : 'PR No.')->addClass('fw-semibold text-nowrap'),
            Column::computed('date')->title('Date')->addClass('text-nowrap'),
            Column::computed('charge_to')->title('Charge to')->addClass('text-nowrap'),
            Column::computed('items_list')->title('Items'),
            Column::make('total_amount')->title($jr ? 'ABC' : 'Total')->addClass('text-end text-nowrap'),
            Column::make('status')->title('Status')->addClass('text-center'),
            Column::computed('action')->title('Action')->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Requests_' . date('YmdHis');
    }
}
