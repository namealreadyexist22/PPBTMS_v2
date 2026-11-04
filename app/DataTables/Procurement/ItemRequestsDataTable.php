<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Enums\ItemRequestStatus;
use App\Models\Procurement\ItemRequest;
use App\Services\Procurement\ItemRequestService;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Item requests, one page at a time: "ours" (the user's office asked) or "incoming" (asked of the offices it
 * procures for, pending first). Narrowed by the page's search (request no. or item) and status.
 */
class ItemRequestsDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblItemRequests';

    /** Offices the user procures for (e.g. MIS for ICT): they get the "Requests to us" tab. */
    public static function procuring(): Collection
    {
        return app(ItemRequestService::class)->procuringOfficeIds(auth()->user());
    }

    public static function tab(): string
    {
        return request('tab') === 'incoming' && static::procuring()->isNotEmpty() ? 'incoming' : 'ours';
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $tab = static::tab();
        $qty = fn ($q) => $q === null ? '—' : rtrim(rtrim(number_format((float) $q, 2), '0'), '.');

        // Every column but the raw ones is escaped by Yajra
        $table = (new EloquentDataTable($query))
            // The request's details (purpose, specs, the decision) in a modal
            ->editColumn('request_no', fn (ItemRequest $ir) => view('procurement.item_requests.extras.list_number', ['ir' => $ir])->render())
            ->addColumn('date', fn (ItemRequest $ir) => $ir->created_at->format('M d, Y'))
            ->addColumn('requestor', fn (ItemRequest $ir) => $ir->requester?->fullname ?? '—')
            // Requests to us: the requesting office's department; ours: the procuring office itself (e.g. MIS)
            ->addColumn('office', fn (ItemRequest $ir) => $tab === 'incoming'
                ? ($ir->requestingOffice?->department ?? $ir->requestingOffice)?->shortName()
                : $ir->procuringOffice?->shortName())
            // The item as when requested
            ->addColumn('item', fn (ItemRequest $ir) => '<span class="fw-semibold">' . e($ir->item_code ?? $ir->item?->code) . '</span> ' . e($ir->item_name ?? $ir->item?->name))
            ->addColumn('requested', fn (ItemRequest $ir) => $qty($ir->quantity) . ' ' . $ir->unitName())
            ->addColumn('approved', fn (ItemRequest $ir) => $ir->isPending() || $ir->status === ItemRequestStatus::Cancelled ? '—' : $qty($ir->approved_quantity))
            ->editColumn('status', fn (ItemRequest $ir) => '<span class="badge bg-' . $ir->status->color() . '">' . e($ir->status->label()) . '</span>')
            ->addColumn('action', fn (ItemRequest $ir) => view('procurement.item_requests.extras.list_action', ['ir' => $ir, 'tab' => $tab])->render())
            ->filter(function ($query) {
                if (($search = $this->searchTerm()) !== '') {
                    $query->where(fn ($w) => $w->where('request_no', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")->orWhere('item_name', 'like', "%{$search}%"));
                }
            })
            ->rawColumns(['request_no', 'item', 'status', 'action'])
            ->setRowId('id');

        // The pending count on the tab, and the per-item totals above the list, follow each decision
        if (static::procuring()->isNotEmpty()) {
            $table->with('pending', ItemRequest::whereIn('procuring_office_id', static::procuring())->where('status', ItemRequestStatus::Pending)->count());
        }
        if ($tab === 'incoming') {
            $table->with('totals', view('procurement.item_requests.extras.totals', ['totals' => static::totals()])->render());
        }

        return $table;
    }

    /** To size the projects: per item and fiscal year, what offices are waiting for and what was approved. */
    public static function totals(): Collection
    {
        return ItemRequest::with('item.unit')->whereIn('procuring_office_id', static::procuring())
            ->whereIn('status', [ItemRequestStatus::Pending, ItemRequestStatus::Approved, ItemRequestStatus::Partial])
            ->get()->groupBy(fn ($r) => $r->fiscal_year . '|' . $r->item_id)
            ->map(fn ($group) => [
                'fiscal_year' => $group->first()->fiscal_year,
                'item'        => $group->first()->item,
                'pending'     => $group->filter->isPending()->sum(fn ($r) => (float) $r->quantity),
                'approved'    => $group->reject->isPending()->sum(fn ($r) => (float) $r->approved_quantity),
                'offices'     => $group->pluck('requesting_office_id')->unique()->count(),
            ])->sortBy(fn ($t) => [$t['fiscal_year'], $t['item']->code])->values();
    }

    public function query(ItemRequest $model): QueryBuilder
    {
        $user = auth()->user();
        $status = ItemRequestStatus::tryFrom((string) request('status'));
        // Offices with their parents: the list shows each one's department
        $query = $model->newQuery()
            ->with(['item.unit', 'requestingOffice.parent.parent.parent', 'procuringOffice.parent.parent.parent', 'requester'])
            ->when($status, fn ($q) => $q->where('status', $status));

        return static::tab() === 'incoming'
            ? $query->whereIn('procuring_office_id', static::procuring())->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest('id')
            : $query->when(! $user->hasRole('Super Admin'), fn ($q) => $q->where('requesting_office_id', $user->office_id))->latest('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            ->minifiedAjax(route('procurement.item-requests.index'), $this->filterFormScript('ir_filter'))
            // Newest first (requests to us: pending first), as the query orders them
            ->parameters($this->listParameters('No item requests here yet.', 'No requests match the search.', ordering: false));
    }

    public function getColumns(): array
    {
        $incoming = static::tab() === 'incoming';

        // The page switches Requestor on and the office column's title with the tab
        return [
            Column::make('request_no')->title('Request')->addClass('text-nowrap'),
            Column::computed('date')->title('Date')->addClass('text-nowrap'),
            Column::computed('requestor')->name('requestor')->title('Requestor')->visible($incoming),
            Column::computed('office')->title($incoming ? 'Department' : 'To')->addClass('fw-semibold'),
            Column::computed('item')->title('Item'),
            Column::computed('requested')->title('Requested')->addClass('text-end text-nowrap'),
            Column::computed('approved')->title('Approved')->addClass('text-end text-nowrap fw-semibold'),
            Column::make('status')->title('Status')->addClass('text-center'),
            Column::computed('action')->title('Action')->addClass('text-end text-nowrap'),
        ];
    }

    protected function filename(): string
    {
        return 'Item_Requests_' . date('YmdHis');
    }
}
