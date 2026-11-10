@extends('procurement.budget.cob_layout')
@php
    $link = fn (?string $show) => route('procurement.budget.cob.organization', array_filter(['fy' => $fiscalYear, 'drafts' => $drafts ? 1 : null, 'show' => $show]));
    $unclassified = $proposals->filter(fn ($p) => $p['hasUnclassified'])->map(fn ($p) => $p['department']->shortName());
@endphp

@section('title', "FY {$fiscalYear} Corporate Operating Budget — Whole Organization")

@section('toolbar')
    <strong>FY {{ $fiscalYear }} COB — Whole organization</strong>
    <form method="GET" style="display: contents;">
        <select name="fy" onchange="this.form.submit()">
            @foreach ($years as $year)<option value="{{ $year }}" @selected($year === $fiscalYear)>FY {{ $year }}</option>@endforeach
        </select>
        @if ($show !== 'all')<input type="hidden" name="show" value="{{ $show }}">@endif
        <label><input type="checkbox" name="drafts" value="1" @checked($drafts) onchange="this.form.submit()"> Include draft PPMPs</label>
    </form>
    <span>|</span>
    @foreach (['all' => 'Everything', 'breakdown' => 'Breakdown', 'summary' => 'Summaries', 'performance' => 'Performance Contracts', 'mooe' => 'Details of MOOE', 'co' => 'Details of CO'] as $key => $label)
        <a class="btn {{ $show === $key ? 'active' : '' }}" href="{{ $link($key === 'all' ? null : $key) }}">{{ $label }}</a>
    @endforeach
    <span class="spacer"></span>
    <a class="btn" href="{{ route('procurement.budget.index', ['fy' => $fiscalYear]) }}">Back</a>
    <button class="primary" onclick="window.print()">Print</button>
@endsection

@section('notice')
    <div class="notice">
        {{ in_array($show, ['all', 'breakdown'], true) ? 'The Breakdown, then ' : '' }}{{ $show === 'breakdown' ? '' : $proposals->count() . ' ' . \Illuminate\Support\Str::plural('department', $proposals->count()) . ' with PPMPs' . ($proposals->isNotEmpty() ? ': ' . $proposals->map(fn ($p) => $p['department']->shortName())->join(', ') : '') . '.' }}
        Departments without {{ $drafts ? '' : 'submitted or approved ' }}PPMPs are left out. This note is not printed.
    </div>
    @if ($drafts)
        <div class="notice">Includes draft and returned PPMPs (each office's latest version).</div>
    @endif
    @if ($unclassified->isNotEmpty() && in_array($show, ['all', 'mooe'], true))
        <div class="notice">MOOE projects without an expense class (shown as <em>Unclassified MOOE</em>) in: {{ $unclassified->join(', ') }}.</div>
    @endif
@endsection

@section('sheets')
    @if (in_array($show, ['all', 'breakdown'], true))
        @include('procurement.budget.partials.cob_breakdown_sheets')
    @endif
    @if ($show !== 'breakdown')
        @foreach ($proposals as $proposal)
            @include('procurement.budget.partials.cob_department_sheets', $proposal + ['show' => $show])
        @endforeach
    @endif
@endsection
