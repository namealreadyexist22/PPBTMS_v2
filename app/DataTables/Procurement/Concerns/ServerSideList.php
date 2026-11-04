<?php

namespace App\DataTables\Procurement\Concerns;

/**
 * A list page whose own search box, filters (a form) and spinner drive a server-side DataTable:
 * one page of rows at a time, searched, filtered and paged in the database.
 */
trait ServerSideList
{
    /** No built-in search box or "Processing" bar: the page has its own (see partials/list_loading). */
    protected function listParameters(string $empty, string $noMatch, bool $ordering = true): array
    {
        return [
            'processing' => false,
            'ordering'   => $ordering,
            'dom'        => "<'table-responsive'tr><'dt-modern-layout dt-modern-footer'l i p>",
            'pageLength' => 25,
            'lengthMenu' => [[10, 25, 50, 100], ['10 entries', '25 entries', '50 entries', '100 entries']],
            'search'     => ['search' => (string) request('q')],
            'language'   => [
                'lengthMenu'  => '_MENU_',
                'emptyTable'  => $empty,
                'zeroRecords' => $noMatch,
                'paginate'    => [
                    'previous' => '<i class="fas fa-chevron-left" style="font-size:0.65rem;"></i>',
                    'next'     => '<i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>',
                ],
            ],
        ];
    }

    /** Every field of the page's filter form but the search box goes with each request (tab, status, year, ...). */
    protected function filterFormScript(string $formId): string
    {
        return '$("#' . $formId . '").serializeArray().forEach(function (f) { if (f.name !== "q") data[f.name] = f.value; });';
    }

    /** The search box's text (the table sends it as search[value]; a first load takes ?q=). */
    protected function searchTerm(): string
    {
        return trim((string) (request('search.value') ?? request('q', '')));
    }

    /** The year picked on the list: this year unless another is chosen; null for "All years". */
    public static function selectedYear(): ?int
    {
        $year = request('year');

        return $year === 'all' ? null : (int) ($year ?: now()->year);
    }
}
