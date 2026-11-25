@extends('BackEnd.layouts.master')

@section('content')
@php
    // ₱48.6M / ₱820K / ₱950
    $peso = function (float $v): string {
        return match (true) {
            $v >= 1_000_000 => '₱' . number_format($v / 1_000_000, 1) . 'M',
            $v >= 1_000     => '₱' . number_format($v / 1_000, 1) . 'K',
            $v == 0         => '₱0',
            default         => '₱' . number_format($v, 2),
        };
    };
    // 0.06% shows as "0.1%", not "0%", so a small commitment still shows
    $pct = fn (float $part, float $whole): float => $whole > 0 ? round($part / $whole * 100, $part / $whole < 0.01 ? 1 : 0) : 0;
    $committedPct = $pct($kpis['ppmp_committed'], $kpis['ppmp_budget']);
    $tones = [
        'warning' => 'bg-warning-subtle text-warning-emphasis',
        'danger'  => 'bg-danger-subtle text-danger-emphasis',
        'accent'  => 'bg-primary-subtle text-primary-emphasis',
    ];
@endphp

<style>
    .dash-card { border: 0; border-radius: 12px; box-shadow: 0 1px 2px rgba(15, 23, 42, .06); }
    .dash-card .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 1rem 1.25rem .75rem; }
    .dash-card .card-title { font-size: .95rem; font-weight: 600; margin: 0; }
    .dash-kpi .label { font-size: .78rem; color: #64748b; margin: 0; }
    .dash-kpi .value { font-size: 1.6rem; font-weight: 600; margin: .15rem 0 0; font-variant-numeric: tabular-nums; }
    .dash-kpi .sub { font-size: .75rem; color: #94a3b8; margin: .15rem 0 0; }
    .dash-row { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .55rem 0; border-top: 1px solid #f1f5f9; font-size: .85rem; }
    .dash-row:first-child { border-top: 0; }
    .dash-row a { color: inherit; text-decoration: none; }
    .dash-row a:hover { text-decoration: underline; }
    .dash-bar { height: 8px; border-radius: 4px; background: #f1f5f9; overflow: hidden; }
    .dash-bar > div { height: 100%; border-radius: 4px; }
    .dash-stage { display: flex; align-items: flex-end; gap: .4rem; height: 130px; }
    .dash-stage > div { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; font-size: .78rem; }
    .dash-stage .col-bar { width: 60%; max-width: 28px; border-radius: 4px 4px 0 0; background: #6250d6; }
    .dash-empty { font-size: .85rem; color: #94a3b8; padding: .5rem 0; }
</style>

{{-- Header and year filter --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0" style="font-size: 1.25rem;">Dashboard</h4>
        <p class="text-muted small mb-0">
            FY {{ $year }}{{ $region ? ' · ' . $region->label() : ' · All regions' }}
        </p>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
        <select name="year" class="form-select form-select-sm" style="width: 130px;" aria-label="Fiscal year" onchange="this.form.submit()">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected($y === $year)>FY {{ $y }}</option>
            @endforeach
        </select>
    </form>
</div>

{{-- KPI row --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card dash-card dash-kpi h-100"><div class="card-body">
            <p class="label">Approved APP budget</p>
            <p class="value">{{ $peso($kpis['app_budget']) }}</p>
            <p class="sub">{{ $kpis['app_count'] ? $kpis['app_count'] . ' approved APP' . ($kpis['app_count'] > 1 ? 's' : '') : 'No approved APP yet' }}</p>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card dash-card dash-kpi h-100"><div class="card-body">
            <p class="label">PPMP budget committed</p>
            <p class="value">{{ $committedPct }}%</p>
            <div class="dash-bar mt-1"><div style="width: {{ min($committedPct, 100) }}%; background: {{ $committedPct >= 90 ? '#eda100' : '#2a78d6' }};"></div></div>
            <p class="sub">{{ $peso($kpis['ppmp_committed']) }} of {{ $peso($kpis['ppmp_budget']) }}</p>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card dash-card dash-kpi h-100"><div class="card-body">
            <p class="label">PR / JR in process</p>
            <p class="value">{{ number_format($kpis['pr_in_process']) }}</p>
            <p class="sub">{{ number_format($kpis['pr_to_receive']) }} waiting to be received</p>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card dash-card dash-kpi h-100"><div class="card-body">
            @if ($kpis['biddings'])
                <p class="label">Ongoing biddings</p>
                <p class="value">{{ number_format($kpis['biddings']['count']) }}</p>
                <p class="sub">{{ $peso($kpis['biddings']['abc']) }} total ABC</p>
            @else
                <p class="label">Approved PPMP budget</p>
                <p class="value">{{ $peso($kpis['ppmp_budget']) }}</p>
                <p class="sub">Your offices' approved PPMPs</p>
            @endif
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Needs your action --}}
    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header"><h5 class="card-title"><i class="fas fa-tasks text-muted me-2"></i>Needs your action</h5></div>
            <div class="card-body pt-2">
                @forelse ($actions as $a)
                    <div class="dash-row">
                        <a href="{{ $a['url'] }}">{{ $a['label'] }}</a>
                        <span class="badge rounded-pill {{ $a['count'] ? $tones[$a['tone']] : 'bg-light text-muted' }}">{{ $a['count'] }}</span>
                    </div>
                @empty
                    <p class="dash-empty mb-0">Nothing is waiting on you.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Small-value pipeline (BAC) or recent activity for everyone else --}}
    <div class="col-lg-6">
        @if ($pipeline)
            @php $max = max(1, collect($pipeline)->max(1)); @endphp
            <div class="card dash-card h-100">
                <div class="card-header"><h5 class="card-title"><i class="fas fa-stream text-muted me-2"></i>Small-value procurement pipeline</h5></div>
                <div class="card-body pt-2">
                    @foreach ($pipeline as [$label, $count])
                        <div class="d-flex align-items-center gap-2 my-2 small">
                            <span class="text-muted" style="width: 130px; flex-shrink: 0;">{{ $label }}</span>
                            <div class="dash-bar flex-grow-1" style="height: 14px;"><div style="width: {{ round($count / $max * 100) }}%; background: #2a78d6;"></div></div>
                            <span class="text-end fw-semibold" style="width: 28px;">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            @include('BackEnd.content.main.partials.activity')
        @endif
    </div>
</div>

@if ($offices !== null || $stages !== null)
<div class="row g-3 mb-3">
    @if ($offices !== null)
    <div class="{{ $stages !== null ? 'col-lg-6' : 'col-12' }}">
        <div class="card dash-card h-100">
            <div class="card-header"><h5 class="card-title"><i class="fas fa-building text-muted me-2"></i>PPMP budget committed by office</h5></div>
            <div class="card-body pt-2">
                @forelse ($offices as $o)
                    <div class="my-2">
                        <div class="d-flex justify-content-between small">
                            <span>{{ $o['office'] }}</span>
                            <span class="text-muted">{{ $peso($o['committed']) }} / {{ $peso($o['estimated']) }} · <span class="fw-semibold text-body">{{ $o['percent'] }}%</span></span>
                        </div>
                        <div class="dash-bar mt-1"><div style="width: {{ min($o['percent'], 100) }}%; background: {{ $o['percent'] >= 90 ? '#eda100' : '#2a78d6' }};"></div></div>
                    </div>
                @empty
                    <p class="dash-empty mb-0">No approved PPMPs for FY {{ $year }}.</p>
                @endforelse
                @if ($offices->isNotEmpty())
                    <p class="text-muted mb-0 mt-2" style="font-size: .75rem;">Committed by submitted PRs ÷ estimated budget. Amber means 90% or more is committed.</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if ($stages !== null)
    @php $stageMax = max(1, max($stages)); @endphp
    <div class="{{ $offices !== null ? 'col-lg-6' : 'col-12' }}">
        <div class="card dash-card h-100">
            <div class="card-header"><h5 class="card-title"><i class="fas fa-gavel text-muted me-2"></i>Ongoing biddings by stage</h5></div>
            <div class="card-body">
                @if (array_sum($stages) === 0)
                    <p class="dash-empty mb-0">No ongoing biddings for {{ $year }}.</p>
                @else
                    <div class="dash-stage">
                        @foreach ($stages as $label => $n)
                            <div>
                                <span class="fw-semibold">{{ $n }}</span>
                                <div class="col-bar" style="height: {{ $n ? round($n / $stageMax * 100) : 2 }}px; {{ $n ? '' : 'background:#e2e8f0;' }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex gap-1 mt-1">
                        @foreach (array_keys($stages) as $label)
                            <span class="flex-fill text-center text-muted" style="font-size: .7rem; flex-basis: 0;">{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
@endif

<div class="row g-3">
    {{-- Upcoming deadlines --}}
    <div class="col-lg-6">
        <div class="card dash-card h-100">
            <div class="card-header"><h5 class="card-title"><i class="far fa-calendar-alt text-muted me-2"></i>Coming up in 30 days</h5></div>
            <div class="card-body pt-2">
                @forelse ($deadlines as $d)
                    <div class="dash-row">
                        <a href="{{ $d['url'] }}">{{ $d['label'] }}</a>
                        <span class="text-nowrap {{ $d['at']->isToday() || $d['at']->isTomorrow() ? 'text-danger fw-semibold' : 'text-muted' }}">
                            {{ $d['at']->isToday() ? 'Today' : ($d['at']->isTomorrow() ? 'Tomorrow' : $d['at']->format('M d')) }}
                        </span>
                    </div>
                @empty
                    <p class="dash-empty mb-0">No deadlines in the next 30 days.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent activity (shown above instead when there is no pipeline) --}}
    @if ($pipeline)
        <div class="col-lg-6">@include('BackEnd.content.main.partials.activity')</div>
    @endif
</div>
@endsection
