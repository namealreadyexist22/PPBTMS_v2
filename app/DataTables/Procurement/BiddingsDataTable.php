<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Enums\BiddingStatus;
use App\Models\Procurement\Bidding;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Biddings of the user's region, one page at a time, newest first. Narrowed by the
 * page's search (bidding no., title or PR / JR no.), status and year. A row opens the bidding.
 */
class BiddingsDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblBiddings';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Every column but the raw ones is escaped by Yajra
        return (new EloquentDataTable($query))
            ->addColumn('label', fn (Bidding $b) => view('procurement.rfqs.extras.list_title', ['row' => $b])->render())
            ->addColumn('lots', fn (Bidding $b) => $b->lots->count())
            ->addColumn('fund', fn (Bidding $b) => $b->fundSource?->code ?? '—')
            ->editColumn('total_abc', fn (Bidding $b) => number_format((float) $b->total_abc, 2))
            ->addColumn('stage_text', fn (Bidding $b) => view('procurement.biddings.extras.list_stage', ['bidding' => $b])->render())
            ->editColumn('status', fn (Bidding $b) => '<span class="badge bg-' . $b->status->color() . '">' . e($b->status->label()) . '</span>')
            ->filter(function ($query) {
                if (($search = $this->searchTerm()) !== '') {
                    $query->where(fn ($w) => $w->where('bid_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('lotItems.line.request', fn ($r) => $r->where('request_no', 'like', "%{$search}%")));
                }
            })
            ->rawColumns(['label', 'stage_text', 'status'])
            // The whole row opens the bidding (see serverList)
            ->setRowAttr([
                'data-url' => fn (Bidding $b) => route('procurement.biddings.show', $b),
                'class'    => 'js-open-row',
                'tabindex' => '0',
            ])
            ->setRowId('id');
    }

    public function query(Bidding $model): QueryBuilder
    {
        $status = BiddingStatus::tryFrom((string) request('status'));
        $year = static::selectedYear();

        return $model->newQuery()
            ->visibleTo(auth()->user())
            ->with(['fundSource', 'lots.items.line'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($year, fn ($q) => $q->ofYear($year))
            ->orderByDesc('series_year')->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            ->minifiedAjax(route('procurement.biddings.index'), $this->filterFormScript('biddings_filter'))
            // Newest first, as the query orders them
            ->parameters($this->listParameters('No biddings yet. Start one and add the Competitive Bidding items of received PRs / JRs.', 'No biddings match the search.', ordering: false));
    }

    public function getColumns(): array
    {
        return [
            Column::make('bid_no')->title('Bidding No.')->addClass('fw-semibold text-nowrap'),
            Column::computed('label')->title('Title'),
            Column::computed('lots')->title('Lots')->addClass('text-center'),
            Column::computed('fund')->title('Fund')->addClass('text-nowrap'),
            Column::make('total_abc')->title('ABC')->addClass('text-end text-nowrap'),
            Column::computed('stage_text')->title('Step'),
            Column::make('status')->title('Status')->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Biddings_' . date('YmdHis');
    }
}
