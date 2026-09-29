<?php

namespace App\Core\DataTables;

use App\Core\DataTables\Traits\HasCoreDataTable;
use App\Core\Models\Menu;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MenusDataTable extends DataTable
{
    use HasCoreDataTable;

    protected ?int $filterParentId = null;

    /**
     * Restrict this table to one menu's direct children — used by the
     * "Manage Submenus" page. Leave unset for the main Menus screen,
     * which now only lists top-level menus (their children show as an
     * inline preview instead of separate rows).
     */
    public function forParent(?int $parentId): static
    {
        $this->filterParentId = $parentId;

        return $this;
    }

    public function dataTable($query)
    {
        $table = (new EloquentDataTable($query))
            ->addColumn('is_nav_label', fn (Menu $menu) => $menu->is_nav
                ? '<i class="fas fa-check text-success"></i>'
                : '<span class="text-muted">—</span>')
            ->addColumn('status', fn (Menu $menu) => $menu->is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>')
            ->addColumn('action', fn (Menu $menu) => view('admin.menus._actions', compact('menu'))->render());

        if ($this->filterParentId === null) {
            $table->addColumn('submenus_preview', function (Menu $menu) {
                $descendants = $menu->allDescendantsFlat();

                return $descendants->isEmpty()
                    ? '<span class="text-muted">—</span>'
                    : $descendants->map(fn ($name) => '• ' . e($name))->implode('<br>');
            });
        }

        return $table
            ->rawColumns(['is_nav_label', 'status', 'action', 'submenus_preview'])
            ->setRowId('id');
    }

    public function query(Menu $model)
    {
        $query = $model->newQuery()->orderBy('order');

        if ($this->filterParentId !== null) {
            $query->where('parent_id', $this->filterParentId);
        } else {
            $query->whereNull('parent_id')->with('children');
        }

        return $query;
    }

    protected function tableId(): string
    {
        return $this->filterParentId !== null ? 'tblSubmenus' : 'tblMenus';
    }

    protected function defaultOrderColumn(): int
    {
        // 'order' sits at a different index depending on which extra
        // columns are included (main screen has 1 more than submenus).
        return $this->filterParentId !== null ? 5 : 6;
    }

    protected function getColumns(): array
    {
        $columns = [
            Column::make('name'),
            Column::make('nav_name')->title('Nav Name'),
            Column::make('route'),
            Column::make('permission_name')->title('Permission'),
        ];

        if ($this->filterParentId === null) {
            $columns[] = Column::computed('submenus_preview')->title('Submenus')
                ->exportable(false)->printable(false)->orderable(false)->searchable(false);
        }

        $columns[] = Column::make('is_nav_label')->title('Is Nav')->orderable(false)->searchable(false);
        $columns[] = Column::make('order');
        $columns[] = Column::make('status')->orderable(false)->searchable(false);
        $columns[] = Column::computed('action')->exportable(false)->printable(false)->width(180)->addClass('text-end');

        return $columns;
    }

    protected function filename(): string
    {
        return 'Menus_' . date('YmdHis');
    }
}