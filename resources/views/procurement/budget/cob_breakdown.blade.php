@extends('procurement.budget.cob_layout')
@php
    // Planned CO + MOOE differs from what the Budget officer allocated (screen only)
    $mismatch = collect($funds)->flatMap(fn ($f) => $f['rows'])->filter(fn ($r) => $r['allocated'] !== null && $r['allocated'] !== $r['co'] + $r['mooe']);
@endphp

@section('title', "COB FY {$fiscalYear} Breakdown per Office / Department")

@section('toolbar')
    <strong>COB FY {{ $fiscalYear }} — Breakdown per Office / Department</strong>
    <form method="GET" style="display: contents;">
        <select name="fy" onchange="this.form.submit()">
            @foreach ($years as $year)<option value="{{ $year }}" @selected($year === $fiscalYear)>FY {{ $year }}</option>@endforeach
        </select>
        <label><input type="checkbox" name="drafts" value="1" @checked($drafts) onchange="this.form.submit()"> Include draft PPMPs</label>
    </form>
    <a class="btn" href="{{ route('procurement.budget.cob.organization', array_filter(['fy' => $fiscalYear, 'drafts' => $drafts ? 1 : null])) }}">Whole organization</a>
    <span class="spacer"></span>
    <a class="btn" href="{{ route('procurement.budget.index', ['fy' => $fiscalYear]) }}">Back to Budget Allocation</a>
    <button class="primary" onclick="window.print()">Print</button>
@endsection

@section('notice')
    @if ($drafts)
        <div class="notice">Includes draft and returned PPMPs (each office's latest version). Uncheck <em>Include draft PPMPs</em> for the submitted and approved ones only.</div>
    @endif
    @if ($mismatch->isNotEmpty())
        <div class="notice">
            CO + MOOE planned in the PPMPs differs from the allocation of:
            {{ $mismatch->map(fn ($r) => $r['department']->shortName() . ' (planned ₱' . number_format(($r['co'] + $r['mooe']) / 100, 2) . ', allocated ₱' . number_format($r['allocated'] / 100, 2) . ')')->join('; ') }}.
            This note is not printed.
        </div>
    @endif
@endsection

@section('sheets')
    @include('procurement.budget.partials.cob_breakdown_sheets')
@endsection
