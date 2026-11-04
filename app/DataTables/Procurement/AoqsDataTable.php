<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Enums\AoqStatus;
use App\Models\Procurement\Aoq;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Abstracts of the user's region, one page at a time, newest first. Narrowed by the page's search
 * (RFQ no., title or supplier), status and year (its RFQ's). A row opens the abstract.
 */
class AoqsDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblAoqs';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Every column but the raw ones is escaped by Yajra
        return (new EloquentDataTable($query))
            ->addColumn('aoq_no', fn (Aoq $a) => $a->rfq->rfq_no)
            ->addColumn('label', fn (Aoq $a) => view('procurement.rfqs.extras.list_title', ['row' => $a->rfq])->render())
            ->addColumn('items_count', fn (Aoq $a) => $a->rfq->items->count())
            ->addColumn('quotations', fn (Aoq $a) => $a->suppliers->count())
            ->addColumn('awarded_to', fn (Aoq $a) => $this->winners($a)->map(fn ($n) => e($n))->implode('<br>') ?: '<span class="text-muted">—</span>')
            ->addColumn('abc', fn (Aoq $a) => number_format((float) $a->rfq->total_abc, 2))
            ->addColumn('award', fn (Aoq $a) => $this->award($a))
            ->editColumn('status', fn (Aoq $a) => '<span class="badge bg-' . $a->status->color() . '">' . e($a->status->label()) . '</span>')
            ->filter(function ($query) {
                if (($search = $this->searchTerm()) !== '') {
                    $query->where(fn ($w) => $w
                        ->whereHas('rfq', fn ($r) => $r->where('rfq_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"))
                        ->orWhereHas('suppliers', fn ($s) => $s->where('name', 'like', "%{$search}%")));
                }
            })
            ->rawColumns(['label', 'awarded_to', 'status'])
            // The whole row opens the abstract (see serverList)
            ->setRowAttr([
                'data-url' => fn (Aoq $a) => route('procurement.aoqs.show', $a),
                'class'    => 'js-open-row',
                'tabindex' => '0',
            ])
            ->setRowId('id');
    }

    /** The suppliers awarded at least one item. */
    protected function winners(Aoq $aoq)
    {
        return $aoq->suppliers->whereIn('id', $aoq->quotes->where('is_awarded', true)->pluck('aoq_supplier_id'))->pluck('name');
    }

    /** The awarded amount: each awarded quote's unit price × its line's quantity. */
    protected function award(Aoq $aoq): string
    {
        $won = $aoq->quotes->where('is_awarded', true);
        if ($won->isEmpty()) {
            return '—';
        }
        $lines = $aoq->rfq->items->keyBy('id');
        $cents = $won->sum(fn ($q) => (int) round(Quantity::toHundredths($lines->get($q->rfq_item_id)?->line?->quantity) * Money::toCents($q->unit_price) / 100));

        return Money::format(Money::fromCents($cents));
    }

    public function query(Aoq $model): QueryBuilder
    {
        $status = AoqStatus::tryFrom((string) request('status'));
        $year = static::selectedYear();

        return $model->newQuery()
            ->visibleTo(auth()->user())
            ->with(['rfq.procurementMode', 'rfq.items.line', 'suppliers', 'quotes'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($year, fn ($q) => $q->whereHas('rfq', fn ($r) => $r->ofYear($year)))
            ->latest('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            ->minifiedAjax(route('procurement.aoqs.index'), $this->filterFormScript('aoqs_filter'))
            // Newest first, as the query orders them
            ->parameters($this->listParameters('No abstracts yet. Start one from an issued RFQ once its quotations are in.', 'No abstracts match the search.', ordering: false));
    }

    public function getColumns(): array
    {
        return [
            Column::computed('aoq_no')->title('AOQ No.')->addClass('fw-semibold text-nowrap'),
            Column::computed('label')->title('Title'),
            Column::computed('items_count')->title('Items')->addClass('text-center'),
            Column::computed('quotations')->title('Quotations')->addClass('text-center'),
            Column::computed('awarded_to')->title('Awarded to'),
            Column::computed('abc')->title('ABC')->addClass('text-end text-nowrap'),
            Column::computed('award')->title('Award')->addClass('text-end text-nowrap fw-semibold'),
            Column::make('status')->title('Status')->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'AOQs_' . date('YmdHis');
    }
}
