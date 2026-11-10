@extends('procurement.budget.cob_layout')
@php
    $show = in_array(request('show'), ['summary', 'performance', 'mooe', 'co'], true) ? request('show') : 'all';
    // Where this printout is opened from: the Budget Allocation (any department) or a Budget Proposal page (its own)
    $proposalUrl ??= fn ($d, array $q) => route('procurement.budget.cob.proposal', [$d] + $q);
    $backUrl ??= route('procurement.budget.index', ['fy' => $fiscalYear, 'fund' => $fund->value]);
    $query = fn (array $extra) => array_filter(['fy' => $years ? $fiscalYear : null, 'drafts' => $drafts ? 1 : null, 'show' => $show === 'all' ? null : $show] + $extra, fn ($v) => $v !== null);
@endphp

@section('title', "FY {$fiscalYear} Budget Proposal — {$department->shortName()}")

@section('toolbar')
    <strong>FY {{ $fiscalYear }} Budget Proposal{{ $departments->isEmpty() ? ' — ' . $department->shortName() : '' }}</strong>
    <form method="GET" style="display: contents;" id="cob_filters">
        @if ($departments->isNotEmpty())
            <select onchange="window.location = this.value">
                @foreach ($departments as $d)
                    <option value="{{ $proposalUrl($d, $query([])) }}" @selected($d->id === $department->id)>{{ $d->acronym ?: $d->name }}</option>
                @endforeach
            </select>
        @endif
        @if ($years)
            <select name="fy" onchange="this.form.submit()">
                @foreach ($years as $year)<option value="{{ $year }}" @selected($year === $fiscalYear)>FY {{ $year }}</option>@endforeach
            </select>
        @endif
        @if ($show !== 'all')<input type="hidden" name="show" value="{{ $show }}">@endif
        <label><input type="checkbox" name="drafts" value="1" @checked($drafts) onchange="this.form.submit()"> Include draft PPMPs</label>
    </form>
    <span>|</span>
    @foreach (['all' => 'All forms', 'summary' => 'Summary', 'performance' => 'Performance Contract', 'mooe' => 'Details of MOOE', 'co' => 'Details of CO'] as $key => $label)
        <a class="btn {{ $show === $key ? 'active' : '' }}" href="{{ $proposalUrl($department, array_filter(['fy' => $years ? $fiscalYear : null, 'drafts' => $drafts ? 1 : null, 'show' => $key === 'all' ? null : $key])) }}">{{ $label }}</a>
    @endforeach
    <span class="spacer"></span>
    <a class="btn" href="{{ $backUrl }}">Back</a>
    <button class="primary" onclick="window.print()">Print</button>
@endsection

@section('notice')
    @if (! $proposal)
        <div class="notice">{{ $department->shortName() }} has no FY {{ $fiscalYear }} Budget Proposal yet: its PAPs are written there. This note is not printed.</div>
    @elseif ($proposal->status !== \App\Enums\BudgetProposalStatus::Evaluated)
        <div class="notice">This Budget Proposal is {{ $proposal->status->value }}{{ $proposal->status === \App\Enums\BudgetProposalStatus::Submitted ? ' (not yet evaluated)' : '' }}; PAPs without a code show blank. This note is not printed.</div>
    @endif
    @if ($drafts)
        <div class="notice">Includes draft and returned PPMPs (each office's latest version). Uncheck <em>Include draft PPMPs</em> for the submitted and approved ones only.</div>
    @endif
    @if ($hasUnclassified && in_array($show, ['all', 'mooe'], true))
        <div class="notice">Some MOOE projects have no expense class, so they show under <em>Unclassified MOOE</em>. Set the expense class on those projects in their PPMP. This note is not printed.</div>
    @endif
@endsection

@section('sheets')
    @include('procurement.budget.partials.cob_department_sheets')
@endsection
