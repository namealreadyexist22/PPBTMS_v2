{{--
    One department's Budget Proposal sheets: Summary, Form A (Performance Contract), Form B (Details of MOOE), Form C (Details of CO).
    Expects what CobReportService::proposal() returns ($department, $blocks, $expenseColumns, $hasUnclassified, $totals),
    plus $fiscalYear, $drafts, $show ('all' | 'summary' | 'performance' | 'mooe' | 'co'), $signatories, $submitters.
--}}
@php
    $amt = fn (int $cents) => $cents === 0 ? '' : number_format($cents / 100, $cents % 100 ? 2 : 0);
    $sub = fn (int $cents) => $cents === 0 ? '-' : number_format($cents / 100, $cents % 100 ? 2 : 0);
    $qty = fn ($q) => $q === null ? '' : rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $deptName = $department->name;
    // Budget per quarter: the total spread evenly; what does not divide goes to Q4
    $quarters = function (int $cents) { $q = intdiv($cents, 4); return [$q, $q, $q, $cents - 3 * $q]; };
    $classCols = $expenseColumns->pluck('id')->all();
    if ($hasUnclassified) { $classCols[] = 0; }
    $classLabel = fn ($id) => $id === 0 ? 'Unclassified MOOE' : $expenseColumns->firstWhere('id', $id)->name;
    // Each block's rows in order: [section|null, pap row]
    $rowsOf = fn ($block) => collect($block['paps'])->map(fn ($p) => [null, $p])
        ->merge(collect($block['sections'])->flatMap(fn ($s) => collect($s['paps'])->map(fn ($p, $i) => [$i === 0 ? $s : null, $p])));
    $blockLabel = fn ($office) => $office ? ($office->acronym ?: $office->name) : $department->shortName();
@endphp
    {{-- ===== Summary: PAP code, title, CO, MOOE, total ===== --}}
    @if (in_array($show, ['all', 'summary'], true))
        <div class="sheet">
            <div class="form-tag">Summary</div>
            <div class="title">FY {{ $fiscalYear }} Budget Proposal<br>{{ $deptName }}</div>
            <table class="cob">
                <thead>
                    <tr><th rowspan="2" style="width: 13%;">PAP Code</th><th rowspan="2">PROGRAMS / ACTIVITIES / PROJECTS (PAP)</th><th colspan="3">PROPOSED BUDGETARY REQUIREMENT (Php)</th></tr>
                    <tr><th style="width: 12%;">CO</th><th style="width: 12%;">MOOE</th><th style="width: 12%;">TOTAL</th></tr>
                </thead>
                <tbody>
                    @forelse ($blocks as $block)
                        @if ($block['office'])<tr class="division"><td></td><td>{{ $block['office']->name }}</td><td></td><td></td><td></td></tr>@endif
                        @foreach ($rowsOf($block) as [$section, $pap])
                            @if ($section)<tr class="section"><td></td><td class="name">{{ $section['letter'] }}. {{ $section['office']->name }}</td><td></td><td></td><td></td></tr>@endif
                            <tr>
                                <td class="code">{{ $pap['code'] }}</td>
                                <td>{{ $pap['title'] }}</td>
                                <td class="r">{{ $amt($pap['co']) }}</td>
                                <td class="r">{{ $amt($pap['mooe']) }}</td>
                                <td class="r"><strong>{{ $amt($pap['co'] + $pap['mooe']) }}</strong></td>
                            </tr>
                        @endforeach
                        @if (count($blocks) > 1)
                            <tr class="subtotal"><td></td><td class="label">Subtotal-{{ $blockLabel($block['office']) }}</td><td class="r">{{ $sub($block['totals']['co']) }}</td><td class="r">{{ $sub($block['totals']['mooe']) }}</td><td class="r">{{ $sub($block['totals']['total']) }}</td></tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="empty">No {{ $drafts ? '' : 'submitted or approved ' }}PPMPs of {{ $department->shortName() }} for FY {{ $fiscalYear }}.</td></tr>
                    @endforelse
                    <tr class="total"><td></td><td>GRAND TOTAL</td><td class="r">{{ $sub($totals['co']) }}</td><td class="r">{{ $sub($totals['mooe']) }}</td><td class="r">{{ $sub($totals['total']) }}</td></tr>
                </tbody>
            </table>
            {{-- As on the form: Submitted by alone; the two evaluators side by side; Recommended by, Approved by --}}
            @include('procurement.budget.partials.cob_signatures', ['rows' => [
                [['Submitted by:', $submitters->get(0)], ['', $submitters->get(1) ?? false]],
                [['Evaluated by:', $signatories['evaluated_1']], ['Evaluated by:', $signatories['evaluated_2']]],
                [['Recommended by:', $signatories['recommended']], ['Approved (by the Authority of the Sugar Board):', $signatories['approved']]],
            ]])
        </div>
    @endif

    {{-- ===== Form A: Performance Contract — indicators with quarterly physical targets, budget per quarter ===== --}}
    @if (in_array($show, ['all', 'performance'], true))
        <div class="sheet">
            <div class="form-tag">Form A</div>
            <div class="title">FY {{ $fiscalYear }} Performance Contract<br>{{ $deptName }}</div>
            @if (empty($blocks))
                <div class="empty">No {{ $drafts ? '' : 'submitted or approved ' }}PPMPs of {{ $department->shortName() }} for FY {{ $fiscalYear }}.</div>
            @else
                <table class="cob" style="font-size: 7.5pt;">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 13%;">Programs / Activities / Projects (PAP)</th>
                            <th rowspan="2" style="width: 17%;">Brief Description</th>
                            <th rowspan="2" style="width: 17%;">Performance Indicator</th>
                            <th colspan="5">Physical Targets</th>
                            <th colspan="5">Budgetary Requirements (Php)</th>
                        </tr>
                        <tr>
                            @foreach (['Q1', 'Q2', 'Q3', 'Q4', 'Total'] as $h)<th style="width: 3.6%;">{{ $h }}</th>@endforeach
                            @foreach (['Q1', 'Q2', 'Q3', 'Q4', 'Total'] as $h)<th style="width: 6%;">{{ $h }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blocks as $block)
                            @php
                                // The block's rows: section headings, then one per indicator (a PAP without any: one blank row)
                                $lines = [];
                                foreach ($rowsOf($block) as [$section, $pap]) {
                                    if ($section) { $lines[] = ['section' => $section]; }
                                    $indicators = $pap['indicators']->values();
                                    // Activities: a description runs down until the next row that has its own
                                    $groupStart = [];
                                    foreach ($indicators as $i => $indicator) {
                                        $groupStart[$i] = ($i === 0 || filled($indicator->description)) ? $i : $groupStart[$i - 1];
                                    }
                                    $groupSize = array_count_values($groupStart);
                                    foreach ($indicators->isEmpty() ? [null] : $indicators as $i => $indicator) {
                                        $lines[] = [
                                            'pap' => $pap, 'indicator' => $indicator, 'first' => $i === 0, 'span' => max(1, $indicators->count()),
                                            'descFirst' => ! $indicator || $groupStart[$i] === $i, 'descSpan' => $indicator ? $groupSize[$groupStart[$i]] : 1,
                                        ];
                                    }
                                }
                                $budget = $quarters($block['totals']['total']);
                            @endphp
                            <tr class="division"><td colspan="13">{{ $block['office']?->name ?? $deptName }}</td></tr>
                            @foreach ($lines as $n => $line)
                                <tr>
                                    @if (isset($line['section']))
                                        <td colspan="8" class="name" style="background: #bdd7ee; font-weight: bold;">{{ $line['section']['letter'] }}. {{ $line['section']['office']->name }}</td>
                                    @else
                                        @php $ind = $line['indicator']; @endphp
                                        @if ($line['first'])
                                            <td rowspan="{{ $line['span'] }}">{{ $line['pap']['title'] }}</td>
                                        @endif
                                        @if ($line['descFirst'])
                                            <td rowspan="{{ $line['descSpan'] }}" style="white-space: pre-line;">{{ $ind?->description ?? ($line['first'] ? $line['pap']['description'] : null) }}</td>
                                        @endif
                                        <td>{{ $ind?->indicator }}</td>
                                        @foreach (['q1', 'q2', 'q3', 'q4', 'total'] as $q)<td class="c">{{ $ind?->$q }}</td>@endforeach
                                    @endif
                                    {{-- The block's budget, spread over its rows --}}
                                    @if ($n === 0)
                                        @foreach ($budget as $cents)<td class="r" rowspan="{{ count($lines) }}">{{ $amt($cents) }}</td>@endforeach
                                        <td class="r" rowspan="{{ count($lines) }}"><strong>{{ $amt($block['totals']['total']) }}</strong></td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr class="subtotal">
                                <td colspan="8" class="label">{{ count($blocks) > 1 ? 'Subtotal-' . $blockLabel($block['office']) : '' }}</td>
                                @foreach ($budget as $cents)<td class="r">{{ $sub($cents) }}</td>@endforeach
                                <td class="r">{{ $sub($block['totals']['total']) }}</td>
                            </tr>
                        @endforeach
                        @if (count($blocks) > 1)
                            <tr class="total">
                                <td colspan="8">GRAND TOTAL</td>
                                @foreach ($quarters($totals['total']) as $cents)<td class="r">{{ $sub($cents) }}</td>@endforeach
                                <td class="r">{{ $sub($totals['total']) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- ===== Form B: Details of MOOE, per expense class ===== --}}
    @if (in_array($show, ['all', 'mooe'], true))
        <div class="sheet">
            <div class="form-tag">Form B</div>
            <div class="title">FY {{ $fiscalYear }} DETAILS OF MOOE<br>{{ $deptName }}</div>
            @php $mooeBlocks = collect($blocks)->filter(fn ($b) => $b['totals']['mooe'] > 0); @endphp
            @if ($mooeBlocks->isEmpty())
                <div class="empty">No MOOE in the PPMPs.</div>
            @else
                <table class="cob" style="font-size: 7.5pt;">
                    <thead>
                        <tr>
                            <th style="width: 9%;">PAP Code</th><th style="width: 22%;">Programs / Activities / Projects</th>
                            @foreach ($classCols as $id)<th>{{ $classLabel($id) }}</th>@endforeach
                            <th>TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mooeBlocks as $block)
                            @if ($block['office'])<tr class="division"><td></td><td colspan="{{ count($classCols) + 2 }}">{{ $block['office']->name }}</td></tr>@endif
                            @foreach ($rowsOf($block)->filter(fn ($r) => $r[1]['mooe'] > 0 || $r[0]) as [$section, $pap])
                                @if ($section && collect($section['paps'])->sum('mooe') > 0)<tr class="section"><td></td><td class="name" colspan="{{ count($classCols) + 2 }}">{{ $section['letter'] }}. {{ $section['office']->name }}</td></tr>@endif
                                @continue($pap['mooe'] === 0)
                                @if ($pap['mooeByLocation']->isNotEmpty())
                                    {{-- Broken down by location: the PAP row, then one row per location --}}
                                    <tr><td class="code">{{ $pap['code'] }}</td><td>{{ $pap['title'] }}</td>@foreach ($classCols as $id)<td></td>@endforeach<td></td></tr>
                                    @foreach ($pap['mooeByLocation'] as $loc)
                                        <tr class="location">
                                            <td></td><td class="name">{{ $loc['location']?->name ?? 'Other / not set' }}</td>
                                            @foreach ($classCols as $id)<td class="r">{{ $amt($loc['byClass'][$id] ?? 0) }}</td>@endforeach
                                            <td class="r">{{ $amt($loc['total']) }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td class="code">{{ $pap['code'] }}</td><td>{{ $pap['title'] }}</td>
                                        @foreach ($classCols as $id)<td class="r">{{ $amt($pap['mooeByClass'][$id] ?? 0) }}</td>@endforeach
                                        <td class="r"><strong>{{ $amt($pap['mooe']) }}</strong></td>
                                    </tr>
                                @endif
                            @endforeach
                            @if (count($blocks) > 1)
                                <tr class="subtotal">
                                    <td></td><td class="label">Subtotal-{{ $blockLabel($block['office']) }}</td>
                                    @foreach ($classCols as $id)<td class="r">{{ $sub($block['totals']['mooeByClass'][$id] ?? 0) }}</td>@endforeach
                                    <td class="r">{{ $sub($block['totals']['mooe']) }}</td>
                                </tr>
                            @endif
                        @endforeach
                        <tr class="total">
                            <td></td><td>GRAND TOTAL</td>
                            @foreach ($classCols as $id)<td class="r">{{ $sub($totals['mooeByClass'][$id] ?? 0) }}</td>@endforeach
                            <td class="r">{{ $sub($totals['mooe']) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- ===== Form C: Details of Capital Outlay, item by item ===== --}}
    @if (in_array($show, ['all', 'co'], true))
        <div class="sheet">
            <div class="form-tag">Form C</div>
            <div class="title">FY {{ $fiscalYear }} DETAILS OF CAPITAL OUTLAY<br>{{ $deptName }}</div>
            @php $coBlocks = collect($blocks)->filter(fn ($b) => $b['totals']['co'] > 0); @endphp
            @if ($coBlocks->isEmpty())
                <div class="empty">No Capital Outlay in the PPMPs.</div>
            @else
                <table class="cob">
                    <thead>
                        <tr><th style="width: 9%;">PAP Code</th><th style="width: 19%;">PROGRAM / ACTIVITIES / PROJECTS (PAP)</th><th style="width: 17%;">ITEMS</th><th style="width: 6%;">QTY.</th><th style="width: 9%;">UNIT COST</th><th style="width: 10%;">TOTAL COST</th><th style="width: 13%;">DEPLOYMENT</th><th>JUSTIFICATION</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($coBlocks as $block)
                            @if ($block['office'])<tr class="division"><td></td><td colspan="7">{{ $block['office']->name }}</td></tr>@endif
                            @foreach ($rowsOf($block)->filter(fn ($r) => $r[1]['co'] > 0 || $r[0]) as [$section, $pap])
                                @if ($section && collect($section['paps'])->sum('co') > 0)<tr class="section"><td></td><td class="name" colspan="7">{{ $section['letter'] }}. {{ $section['office']->name }}</td></tr>@endif
                                @continue($pap['co'] === 0)
                                <tr><td class="code">{{ $pap['code'] }}</td><td colspan="7"><strong>{{ $pap['title'] }}</strong></td></tr>
                                @foreach ($pap['coByLocation'] as $group)
                                    @foreach ($group['items'] as $item)
                                        <tr>
                                            <td></td>
                                            <td>{{ $loop->first ? $group['location']?->name : '' }}</td>
                                            <td>{{ $item->item?->name ?? $item->description }}</td>
                                            <td class="c">{{ $qty($item->quantity) }}</td>
                                            <td class="r">{{ $item->unit_cost !== null ? number_format((float) $item->unit_cost, (float) $item->unit_cost == floor((float) $item->unit_cost) ? 0 : 2) : '' }}</td>
                                            <td class="r"><strong>{{ $amt(\App\Support\Money::toCents($item->estimated_budget)) }}</strong></td>
                                            <td>{{ $item->deployment }}</td>
                                            <td>{{ $item->justification }}</td>
                                        </tr>
                                    @endforeach
                                    @if ($group['location'] || $pap['coByLocation']->count() > 1)
                                        <tr><td></td><td></td><td><strong>subtotal - {{ $group['location']?->name ?? 'other' }}</strong></td><td></td><td></td><td class="r"><strong>{{ $amt($group['total']) }}</strong></td><td></td><td></td></tr>
                                    @endif
                                @endforeach
                            @endforeach
                            @if (count($blocks) > 1)
                                <tr class="subtotal"><td></td><td class="label" colspan="4">Subtotal-{{ $blockLabel($block['office']) }}</td><td class="r">{{ $sub($block['totals']['co']) }}</td><td></td><td></td></tr>
                            @endif
                        @endforeach
                        <tr class="total"><td></td><td colspan="4">TOTAL - {{ $department->shortName() }}</td><td class="r">{{ $sub($totals['co']) }}</td><td></td><td></td></tr>
                    </tbody>
                </table>
            @endif
        </div>
    @endif
