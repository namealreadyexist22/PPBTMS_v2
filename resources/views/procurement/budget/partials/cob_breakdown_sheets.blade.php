{{-- Breakdown per Office / Department sheets (Corporate Fund, SIDA Fund). Expects $funds, $fiscalYear, $signatories. --}}
@php
    // Whole pesos as on the form; centavos only when there are any; blank for none
    $amt = fn (int $cents) => $cents === 0 ? '' : number_format($cents / 100, $cents % 100 ? 2 : 0);
    $grand = collect($funds)->reduce(fn ($c, $f) => collect($c)->map(fn ($v, $k) => $v + $f['totals'][$k])->all(), ['ps' => 0, 'co' => 0, 'mooe' => 0, 'total' => 0]);
@endphp
@foreach ($funds as $key => $f)
    <div class="sheet">
        <div class="title">
            SRA FY {{ $fiscalYear }} Corporate Operating Budget (Corporate and SIDA Fund)<br>
            <span class="sub">Breakdown per Office / Department</span>
        </div>
        <table class="cob">
            <thead>
                <tr><th rowspan="2" style="width: 34%;">Office / Department</th><th rowspan="2" style="width: 10%;">Acronym</th><th colspan="4">Budgetary Requirement</th></tr>
                <tr><th>Personnel Services</th><th>CO</th><th>MOOE</th><th>Total</th></tr>
            </thead>
            <tbody>
                <tr class="fund"><td>{{ $f['fund'] === \App\Enums\FundGroup::Sida ? 'SIDA Fund' : 'Corporate Fund' }}</td><td></td><td></td><td></td><td></td><td></td></tr>
                @forelse ($f['rows'] as $row)
                    <tr>
                        <td>{{ $row['department']->name }}</td>
                        <td class="c"><em>{{ $row['department']->acronym }}</em></td>
                        <td class="r">{{ $amt($row['ps']) }}</td>
                        <td class="r">{{ $amt($row['co']) }}</td>
                        <td class="r">{{ $amt($row['mooe']) }}</td>
                        <td class="r"><strong>{{ number_format($row['total'] / 100, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No allocations or PPMPs for FY {{ $fiscalYear }} yet.</td></tr>
                @endforelse
                <tr class="grand">
                    <td>Total - {{ $f['fund'] === \App\Enums\FundGroup::Sida ? 'SIDA Fund' : 'Corporate Fund' }}</td><td></td>
                    <td class="r">{{ $f['totals']['ps'] ? number_format($f['totals']['ps'] / 100, 2) : '' }}</td>
                    <td class="r">{{ $amt($f['totals']['co']) }}</td>
                    <td class="r">{{ $amt($f['totals']['mooe']) }}</td>
                    <td class="r">{{ number_format($f['totals']['total'] / 100, 2) }}</td>
                </tr>
                @if ($loop->last)
                    <tr class="total">
                        <td>GRAND TOTAL</td><td></td>
                        <td class="r">{{ $grand['ps'] ? number_format($grand['ps'] / 100, 2) : '' }}</td>
                        <td class="r">{{ $amt($grand['co']) }}</td>
                        <td class="r">{{ $amt($grand['mooe']) }}</td>
                        <td class="r">{{ number_format($grand['total'] / 100, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
        @if ($loop->last)
            @include('procurement.budget.partials.cob_signatures', ['rows' => [
                [['Endorsed by:', $signatories['endorsed_1']], ['', $signatories['endorsed_2']]],
                [['Recommended by:', $signatories['recommended']], ['Approved (by the Authority of the Sugar Board):', $signatories['approved']]],
            ]])
        @endif
    </div>
@endforeach
