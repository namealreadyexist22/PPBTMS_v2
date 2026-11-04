<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Enums\RfqStatus;
use App\Models\Procurement\Rfq;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * RFQs of the user's region, one page at a time, newest first (drafts on top).
 * Narrowed by the page's search (RFQ no., title or PR / JR no.), status and year. A row opens its RFQ.
 */
class RfqsDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblRfqs';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Every column but the raw ones is escaped by Yajra
        return (new EloquentDataTable($query))
            ->editColumn('rfq_no', fn (Rfq $r) => $r->rfq_no ?? 'Draft')
            ->addColumn('date', fn (Rfq $r) => ($r->issued_at ?? $r->created_at)->format('M d, Y'))
            ->addColumn('label', fn (Rfq $r) => view('procurement.rfqs.extras.list_title', ['row' => $r])->render())
            ->addColumn('items_list', fn (Rfq $r) => view('procurement.partials.items_cell', ['lines' => $r->items->map->line->filter()->values()])->render())
            ->addColumn('requests_list', fn (Rfq $r) => $r->requests()->map(fn ($pr) => e($pr->title()))->implode('<br>') ?: '—')
            ->addColumn('mode', fn (Rfq $r) => $r->procurementMode?->code ?? '—')
            ->editColumn('total_abc', fn (Rfq $r) => number_format((float) $r->total_abc, 2))
            ->editColumn('status', fn (Rfq $r) => '<span class="badge bg-' . $r->status->color() . '">' . e($r->status->label()) . '</span>')
            ->filter(function ($query) {
                if (($search = $this->searchTerm()) !== '') {
                    $query->where(fn ($w) => $w->where('rfq_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('items.line.request', fn ($r) => $r->where('request_no', 'like', "%{$search}%")));
                }
            })
            ->rawColumns(['label', 'items_list', 'requests_list', 'status'])
            // The whole row opens the RFQ (see serverList)
            ->setRowAttr([
                'data-url' => fn (Rfq $r) => route('procurement.rfqs.show', $r),
                'class'    => 'js-open-row',
                'tabindex' => '0',
            ])
            ->setRowId('id');
    }

    public function query(Rfq $model): QueryBuilder
    {
        $status = RfqStatus::tryFrom((string) request('status'));
        $year = static::selectedYear();

        return $model->newQuery()
            ->visibleTo(auth()->user())
            ->with(['procurementMode', 'items.line.request'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($year, fn ($q) => $q->ofYear($year))
            ->orderByRaw('rfq_no is null desc')
            ->orderByDesc('series_year')->orderByDesc('series')->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            ->minifiedAjax(route('procurement.rfqs.index'), $this->filterFormScript('rfqs_filter'))
            // Newest first (drafts on top), as the query orders them
            ->parameters($this->listParameters('No RFQs yet. Start one and add items from submitted PRs / JRs.', 'No RFQs match the search.', ordering: false));
    }

    public function getColumns(): array
    {
        return [
            Column::make('rfq_no')->title('RFQ No.')->addClass('fw-semibold text-nowrap'),
            Column::computed('date')->title('Date')->addClass('text-nowrap'),
            Column::computed('label')->title('Title'),
            Column::computed('items_list')->title('Items'),
            Column::computed('requests_list')->title('PR / JR')->addClass('text-nowrap'),
            Column::computed('mode')->title('Mode')->addClass('text-nowrap'),
            Column::make('total_abc')->title('ABC')->addClass('text-end text-nowrap'),
            Column::make('status')->title('Status')->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'RFQs_' . date('YmdHis');
    }
}
