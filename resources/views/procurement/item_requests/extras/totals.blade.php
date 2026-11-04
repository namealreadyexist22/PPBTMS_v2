{{-- Requests to us: per item and fiscal year, what offices are waiting for and what was approved (to size the projects). Expects $totals. --}}
@php $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.'); @endphp
@if ($totals->isNotEmpty())
    <div class="small fw-semibold mb-1">Requested from your office, per item</div>
    <div class="table-responsive border rounded-3 mb-4">
        <table class="table table-sm align-middle small mb-0">
            <thead class="table-light"><tr class="text-nowrap"><th class="ps-3">FY</th><th>Item</th><th class="text-center">Offices</th><th class="text-end">Pending</th><th class="text-end pe-3">Approved</th></tr></thead>
            <tbody>
                @foreach ($totals as $t)
                    <tr>
                        <td class="ps-3">{{ $t['fiscal_year'] }}</td>
                        <td><span class="fw-semibold">{{ $t['item']->code }}</span> {{ $t['item']->name }}</td>
                        <td class="text-center">{{ $t['offices'] }}</td>
                        <td class="text-end text-nowrap">{{ $qty($t['pending']) }} {{ $t['item']->unit?->name }}</td>
                        <td class="text-end text-nowrap pe-3">{{ $qty($t['approved']) }} {{ $t['item']->unit?->name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
