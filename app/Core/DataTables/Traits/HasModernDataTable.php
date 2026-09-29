<?php

namespace App\Core\DataTables\Traits;

use Yajra\DataTables\Html\Builder as HtmlBuilder;

trait HasModernDataTable
{
    /**
     * Enforce column declarations on matching datatable modules.
     */
    abstract public function getColumns(): array;

    /**
     * Reusable layout renderer engine for global management tables.
     */
    protected function applyModernHtmlSettings(HtmlBuilder $builder): HtmlBuilder
    {
        return $builder
            ->setTableId($this->tableId ?? 'tblData')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0)
            ->parameters([
                'autoWidth'  => false,
                'width'      => '100%',
                'dom'        => "<'dt-modern-layout'l f>" .
                                "<'table-responsive'tr>" .
                                "<'dt-modern-layout dt-modern-footer'i p>",
                'pagingType' => 'simple_numbers',
                'lengthMenu' => [
                    [10, 25, 50, -1],
                    ['10 entries', '25 entries', '50 entries', 'All']
                ],
                'language' => [
                    'search'            => '',
                    'searchPlaceholder' => 'Search...',
                    'lengthMenu'        => '_MENU_', // Keeps only the dropdown box visible
                    'paginate'          => [
                        'previous' => '<i class="fas fa-chevron-left" style="font-size:0.65rem;"></i>',
                        'next'     => '<i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>'
                    ]
                ]
            ]);
    }

    /**
     * Global action dropdown rendering block generator
     */
    protected function renderActionsDropdown(array $actions): string
    {
        $html = '
        <div class="dropdown">
            <button class="btn btn-sm btn-light border dropdown-toggle no-caret px-2.5 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 6px; background-color: #ffffff;">
                <i class="fas fa-ellipsis-h text-muted" style="font-size: 0.85rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2" style="font-size: 0.85rem; min-width: 140px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05)!important;">';

        foreach ($actions as $action) {
            if ($action === 'divider') {
                $html .= '<li><hr class="dropdown-divider my-1" style="border-color: #f1f5f9;"></li>';
                continue;
            }

            $icon = $action['icon'] ?? '';
            $class = $action['class'] ?? 'text-secondary';
            $url = $action['url'] ?? '#';
            $label = $action['label'] ?? 'Action';
            $attrs = $action['attributes'] ?? '';

            $html .= "<li><a class=\"dropdown-item py-2 {$class}\" href=\"{$url}\" {$attrs}><i class=\"{$icon} me-2 opacity-75\"></i> {$label}</a></li>";
        }

        $html .= '</ul></div>';
        return $html;
    }
}
