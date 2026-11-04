<?php

namespace App\DataTables\Procurement;

use App\Core\DataTables\Traits\HasModernDataTable;
use App\DataTables\Procurement\Concerns\ServerSideList;
use App\Enums\AoqStatus;
use App\Models\Procurement\Aoq;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * The abstracts the BAC assigned to the user (Super Admin: all sent to a TWG), one page at a time:
 * waiting for the evaluation first (oldest sent first), then the evaluated ones (latest first).
 * Narrowed by the page's search (AOQ no. or title), show (for evaluation / evaluated) and year (its RFQ's).
 */
class TwgDataTable extends DataTable
{
    use HasModernDataTable, ServerSideList;

    protected string $tableId = 'tblTwg';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Every column but the raw ones is escaped by Yajra
        return (new EloquentDataTable($query))
            ->addColumn('aoq_no', fn (Aoq $a) => $a->title())
            ->addColumn('label', fn (Aoq $a) => view('procurement.rfqs.extras.list_title', ['row' => $a->rfq])->render())
            ->addColumn('mode', fn (Aoq $a) => $a->rfq->procurementMode?->name ?? '—')
            // When the BAC sent it to the TWG
            ->addColumn('date', fn (Aoq $a) => $a->sent_to_twg_at?->format('M d, Y') ?? '—')
            ->editColumn('status', fn (Aoq $a) => '<span class="badge bg-' . $a->status->color() . '">' . e($a->status->label()) . '</span>')
            ->addColumn('action', fn (Aoq $a) => $a->status === AoqStatus::ForEvaluation
                ? '<a href="' . e(route('procurement.twg.show', $a)) . '" class="btn btn-sm btn-primary py-0">Evaluate</a>'
                : '<a href="' . e(route('procurement.twg.show', $a)) . '" class="btn btn-sm btn-outline-secondary py-0">View</a>')
            ->filter(function ($query) {
                if (($search = $this->searchTerm()) !== '') {
                    $query->whereHas('rfq', fn ($r) => $r->where('rfq_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
                }
            })
            ->rawColumns(['label', 'status', 'action'])
            // The whole row opens the evaluation (see serverList)
            ->setRowAttr([
                'data-url' => fn (Aoq $a) => route('procurement.twg.show', $a),
                'class'    => 'js-open-row',
                'tabindex' => '0',
            ])
            ->setRowId('id');
    }

    /** Assigned to the user (Super Admin: every abstract sent to a TWG). */
    public static function assigned(): QueryBuilder
    {
        $user = auth()->user();

        return Aoq::query()
            ->when(! $user->hasRole('Super Admin'), fn ($q) => $q->whereHas('evaluators', fn ($e) => $e->whereKey($user->id)))
            // With the TWG now, or evaluated by it before
            ->where(fn ($w) => $w->where('status', AoqStatus::ForEvaluation)->orWhereNotNull('evaluated_by'));
    }

    public function query(Aoq $model): QueryBuilder
    {
        $year = static::selectedYear();
        $waiting = AoqStatus::ForEvaluation->value;

        return static::assigned()
            ->with(['rfq.procurementMode', 'rfq.items.line'])
            ->when(request('show') === 'pending', fn ($q) => $q->where('status', AoqStatus::ForEvaluation))
            ->when(request('show') === 'done', fn ($q) => $q->where('status', '!=', AoqStatus::ForEvaluation))
            ->when($year, fn ($q) => $q->whereHas('rfq', fn ($r) => $r->ofYear($year)))
            // Waiting first, oldest sent first; then the evaluated ones, latest first
            ->orderByRaw('case when status = ? then 0 else 1 end', [$waiting])
            ->orderByRaw('case when status = ? then sent_to_twg_at end asc', [$waiting])
            ->orderByDesc('evaluated_at')->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder())
            ->minifiedAjax(route('procurement.twg.index'), $this->filterFormScript('twg_filter'))
            // Waiting first, as the query orders them
            ->parameters($this->listParameters('No abstracts assigned to you yet.', 'No abstracts match the search.', ordering: false));
    }

    public function getColumns(): array
    {
        return [
            Column::computed('aoq_no')->title('AOQ')->addClass('fw-semibold text-nowrap'),
            Column::computed('label')->title('Items'),
            Column::computed('mode')->title('Mode'),
            Column::computed('date')->title('Date')->addClass('text-nowrap'),
            Column::make('status')->title('Status')->addClass('text-center'),
            Column::computed('action')->title('')->addClass('text-end text-nowrap'),
        ];
    }

    protected function filename(): string
    {
        return 'TWG_' . date('YmdHis');
    }
}
