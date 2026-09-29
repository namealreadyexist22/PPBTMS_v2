<?php

namespace App\Core\DataTables;

use App\Core\DataTables\Traits\HasCoreDataTable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class LogsDataTable extends DataTable
{

    use HasCoreDataTable;
    
    protected function tableId(): string
    {
        return 'tblLogs';
    }

    protected function defaultOrderColumn(): int
    {
        return 3;
    }

    protected function defaultOrderDirection(): string
    {
        return 'desc';
    }

    public function dataTable($query)
    {
        return (new EloquentDataTable($query))
            ->addColumn('causer_name', fn (Activity $activity) => $activity->causer?->fullname ?? 'System')
            ->addColumn('subject', fn (Activity $activity) => $activity->subject_type
                ? class_basename($activity->subject_type) . ($activity->subject_id ? " #{$activity->subject_id}" : '')
                : '—')
            ->editColumn('created_at', fn (Activity $activity) => $activity->created_at->format('M d, Y h:i A'))
            ->setRowId('id');
    }

    public function query(Activity $model): Builder
    {
        return $model->newQuery()->with('causer')->latest();
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('tblLogs')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(3, 'desc')
            ->selectStyleSingle();
    }

    protected function getColumns(): array
    {
        return [
            Column::make('causer_name')->title('User')->orderable(false)->searchable(false),
            Column::make('description')->title('Action'),
            Column::make('subject')->title('Record')->orderable(false)->searchable(false),
            Column::make('created_at')->title('When'),
        ];
    }

    protected function filename(): string
    {
        return 'ActivityLogs_' . date('YmdHis');
    }
}