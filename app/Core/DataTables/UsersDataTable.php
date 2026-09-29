<?php

namespace App\Core\DataTables;

use App\Models\User;
use App\Core\DataTables\Traits\HasModernDataTable; // Import your custom trait
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UsersDataTable extends DataTable
{
    use HasModernDataTable;

    protected string $tableId = 'tblUsers';

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
           ->addColumn('user_details', function($row) {
                return view('BackEnd.auth.extras.user_details', [
                    'user' => $row
                ])->render();
            })
            ->addColumn('is_activated', function($row) {
                return view('BackEnd.auth.extras.user_is_activated', ['id' => $row->id,'is_activated' => $row->is_activated])->render();
            })
            ->addColumn('action', function($row) {
                return view('BackEnd.auth.extras.user_action', [
                    'user' => $row
                ])->render();
            })
            ->rawColumns(['user_details', 'is_activated','action'])
            ->setRowId('id');
    }

    public function query(User $model): QueryBuilder
    {
        return $model->newQuery()->with('roles');
    }

    public function html(): HtmlBuilder
    {
        return $this->applyModernHtmlSettings($this->builder());
    }

    public function getColumns(): array
    {
        return [
            Column::computed('user_details')->title('User Details')->addClass('text-right'),
            Column::computed('is_activated')->title('Status')->addClass('text-center'),
            Column::computed('action')->title('')->addClass('text-center')->width(50),
        ];
    }

    protected function filename(): string
    {
        return 'Users_' . date('YmdHis');
    }
}
