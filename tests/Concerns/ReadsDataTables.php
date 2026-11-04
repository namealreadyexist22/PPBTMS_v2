<?php

namespace Tests\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;

/** Reads a list page's server-side DataTable as the page asks for it: its columns, the search box (q) and the filters. */
trait ReadsDataTables
{
    protected function tableResponse(string $route, string $dataTable, array $params = []): TestResponse
    {
        $columns = collect(app($dataTable)->getColumns())->map(fn ($c) => [
            'data' => $c->data, 'name' => $c->name, 'searchable' => $c->searchable ? 'true' : 'false', 'orderable' => $c->orderable ? 'true' : 'false', 'search' => ['value' => ''],
        ])->all();
        $search = $params['q'] ?? '';
        unset($params['q']);

        return $this->getJson(route($route, ['draw' => 1, 'start' => 0, 'length' => 25, 'columns' => $columns, 'search' => ['value' => $search]] + $params),
            ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    }

    /** One page of rows. */
    protected function tableRows(string $route, string $dataTable, array $params = []): Collection
    {
        return collect($this->tableResponse($route, $dataTable, $params)->json('data'));
    }
}
