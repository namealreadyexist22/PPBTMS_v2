<?php

namespace App\Core\DataTables\Traits;

use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;

/**
 * Shared, configurable boilerplate for the base-template's admin
 * DataTables (Menus, Roles, Permissions, Logs, etc). A class using this
 * trait only needs to implement tableId() plus the usual dataTable(),
 * query(), and getColumns() — every other hook below has a sensible
 * default and is only overridden when a specific table needs to differ.
 */
trait HasCoreDataTable
{
    /** Provided by Yajra's DataTable base class that every user of this trait extends. */
    abstract public function builder();

    /** Every DataTable class defines its own column set. */
    abstract protected function getColumns(): array;

    /** Unique DOM id for this table; must match the JS ajax.reload() calls in the Blade view. */
    abstract protected function tableId(): string;

    protected function defaultOrderColumn(): int
    {
        return 0;
    }

    protected function defaultOrderDirection(): string
    {
        return 'asc';
    }

    /** Rows per page on initial load. */
    protected function pageLength(): int
    {
        return 10;
    }

    /** Options shown in the "rows per page" dropdown. */
    protected function lengthMenu(): array
    {
        return [10, 25, 50, 100];
    }

    protected function responsive(): bool
    {
        return true;
    }

    /**
     * 'single', 'multi', or null to disable row selection entirely
     * (e.g. a read-only table like Logs might not need selection at all).
     */
    protected function selectStyle(): ?string
    {
        return 'null';
    }

    /**
     * Export/print/reload toolbar buttons. Off by default — turn on for
     * a specific table by overriding withButtons() to true. Requires the
     * yajra/laravel-datatables-buttons package and its JS assets loaded.
     */
    protected function withButtons(): bool
    {
        return false;
    }

    /** Override to customize which buttons show when withButtons() is true. */
    protected function buttons(): array
    {
        return [
            Button::make('excel'),
            Button::make('csv'),
            Button::make('pdf'),
            Button::make('print'),
            Button::make('reset'),
            Button::make('reload'),
        ];
    }

    /** Override for a non-default ajax endpoint (rare — usually the table's own route is used). */
    protected function ajaxUrl(): ?string
    {
        return null;
    }

    /** Raw JS snippet string for customizing the ajax request payload, e.g. 'function(d) { d.foo = "bar"; }'. */
    protected function ajaxData(): ?string
    {
        return null;
    }

    /** DataTables i18n overrides — empty means use the global/default language. */
    protected function language(): array
    {
        return [];
    }

    /** Escape hatch for any raw DataTables JS option not covered by a dedicated hook above. */
    protected function extraParameters(): array
    {
        return [];
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->builder()
            ->setTableId($this->tableId())
            ->columns($this->getColumns())
            ->orderBy($this->defaultOrderColumn(), $this->defaultOrderDirection())
            ->responsive($this->responsive())
            ->pageLength($this->pageLength())
            ->lengthMenu($this->lengthMenu())
            ->parameters($this->extraParameters());

        if ($this->ajaxUrl()) {
            $builder->minifiedAjax($this->ajaxUrl(), null, $this->ajaxData());
        } else {
            $builder->minifiedAjax();
        }

        match ($this->selectStyle()) {
            'single' => $builder->selectStyleSingle(),
            'multi' => $builder->select(['style' => 'multi']),
            default => null,
        };

        if ($this->withButtons()) {
            $builder->buttons($this->buttons());
        }

        if (! empty($this->language())) {
            $builder->language($this->language());
        }

        return $builder;
    }

    protected function filename(): string
    {
        return class_basename(static::class) . '_' . date('YmdHis');
    }
}