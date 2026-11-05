<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Enums\RequestStatus;
use App\Models\Procurement\PurchaseRequest;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * PR Receiving: the submitted PRs / JRs of the user's region the BAC has yet to receive (oldest first),
 * or those it received (latest first). Narrowed by the page's search (number, purpose or office) and year.
 */
class PrReceivingDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblPrReceiving';

    public static function tab(): string
    {
        return request('tab') === 'received' ? 'received' : 'pending';
    }

    /** Submitted PRs / JRs of the user's region (Super Admin: all). */
    public static function region(): QueryBuilder
    {
        $user = auth()->user();

        return PurchaseRequest::query()
            ->when(! $user->hasRole('Super Admin'), fn ($q) => $q->whereHas('ppmp', fn ($p) => $p->where('region', $user->region ?? \App\Enums\Region::Lm)));
    }

    /** Waiting to be received: submitted, not returned to the office. */
    public static function pending(): QueryBuilder
    {
        return static::region()->where('status', RequestStatus::Submitted)->whereNull('returned_at')->whereNull('received_at');
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Every column but the raw ones is escaped by Yajra
        return (new EloquentDataTable($query))
            ->editColumn('request_no', fn (PurchaseRequest $r) => $r->title())
            ->addColumn('submitted', fn (PurchaseRequest $r) => $r->submitted_at?->format('M d, Y'))
            ->addColumn('office', fn (PurchaseRequest $r) => $r->office?->shortName())
            ->addColumn('items_list', fn (PurchaseRequest $r) => view('procurement.partials.items_cell', ['lines' => $r->items])->render())
            ->editColumn('total_amount', fn (PurchaseRequest $r) => number_format((float) $r->total_amount, 2))
            // Where its items go next: a bidding (Competitive Bidding items) or an RFQ
            ->addColumn('route_to', fn (PurchaseRequest $r) => $r->procurementLabel() === 'FOR BIDDING'
                ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">For bidding</span>'
                : '<span class="badge bg-light text-dark border">RFQ</span>')
            ->addColumn('received', fn (PurchaseRequest $r) => $r->received_at
                ? e($r->received_at->format('M d, Y')) . '<div class="text-muted">' . e($r->receiver?->fullname ?? '') . '</div>'
                : '—')
            ->addColumn('action', fn (PurchaseRequest $r) => view('procurement.receiving.extras.list_action', ['row' => $r])->render())
            ->filter(function ($query) {
                if (($search = $this->searchTerm()) !== '') {
                    $query->where(fn ($w) => $w->where('request_no', 'like', "%{$search}%")->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('office', fn ($o) => $o->where('acronym', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")));
                }
            })
            // The tab's count of PRs / JRs still to receive
            ->with('pending', static::pending()->count())
            ->rawColumns(['items_list', 'route_to', 'received', 'action'])
            ->setRowId('id');
    }

    public function query(PurchaseRequest $model): QueryBuilder
    {
        $year = static::selectedYear();
        $query = (static::tab() === 'received' ? static::region()->whereNotNull('received_at')->latest('received_at') : static::pending()->oldest('submitted_at'))
            ->with(['office', 'items.ppmpItem.procurementMode', 'receiver']);

        return $query->when($year, fn ($q) => $q->ofYear($year))->orderBy('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            ->minifiedAjax(route('procurement.receiving.index'), $this->filterFormScript('receiving_filter'))
            // To receive: oldest first; received: latest first (as the query orders them)
            ->parameters($this->listParameters('Nothing here.', 'No requests match the search.', ordering: false));
    }

    public function getColumns(): array
    {
        $received = static::tab() === 'received';

        return [
            Column::make('request_no')->title('PR / JR')->addClass('fw-semibold text-nowrap'),
            Column::computed('submitted')->title('Submitted')->addClass('text-nowrap'),
            Column::computed('office')->title('Office')->addClass('text-nowrap'),
            Column::computed('items_list')->title('Items'),
            Column::make('total_amount')->title('Total')->addClass('text-end text-nowrap'),
            Column::computed('route_to')->title('Goes to')->addClass('text-center'),
            Column::computed('received')->name('received')->title('Received')->addClass('text-nowrap')->visible($received),
            Column::computed('action')->title('')->addClass('text-end text-nowrap'),
        ];
    }

    protected function filename(): string
    {
        return 'PR_Receiving_' . date('YmdHis');
    }
}
