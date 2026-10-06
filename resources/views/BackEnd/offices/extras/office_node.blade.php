{{-- One unit of the organization tree, with its children below it --}}
@php
    $typeColors = ['department' => 'bg-dark', 'division' => 'bg-secondary', 'section' => 'bg-light text-dark border'];
    $children = $childrenOf->get($office->id, collect());
    $nextType = $office->type === 'department' ? 'division' : 'section';
@endphp
<div class="org-node">
    <div class="org-row d-flex align-items-center gap-3 px-4 py-2 border-bottom {{ $depth === 0 ? 'bg-light' : '' }} {{ $office->is_active ? '' : 'opacity-50' }}"
         style="padding-left: {{ 1.5 + $depth * 1.75 }}rem !important;">
        <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="min-width: 0;">
            @if ($depth)<i class="fas fa-level-up-alt fa-rotate-90 text-muted small"></i>@endif
            <span class="badge {{ $typeColors[$office->type] ?? 'bg-light text-dark border' }}" style="min-width: 84px;">{{ $office->typeLabel() }}</span>
            <span class="text-muted small">{{ $office->code }}</span>
            @if ($office->acronym)<span class="{{ $depth === 0 ? 'fw-bold' : 'fw-semibold' }}">{{ $office->acronym }}</span>@endif
            <span class="small">{{ $office->acronym ? '— ' : '' }}{{ $office->name }}</span>
            @if ($office->isDepartment())
                <span class="badge {{ $office->budget_fund === \App\Enums\FundGroup::Sida ? 'bg-warning text-dark' : 'bg-success-subtle text-success border' }}" title="Budget fund">{{ $office->budget_fund?->label() ?? 'COB' }} budget</span>
            @endif
            @if ($office->is_consolidating)<span class="badge bg-primary-subtle text-primary border">Approves PPMPs</span>@endif
            @unless ($office->is_active)<span class="badge bg-secondary">Inactive</span>@endunless
        </div>

        <div class="d-flex align-items-center gap-2 small text-muted text-nowrap flex-shrink-0">
            <span title="Head"><i class="fas fa-user-tie me-1"></i>{{ $office->head?->fullname ?? 'No head' }}</span>
            <span title="Users"><i class="fas fa-users me-1"></i>{{ $office->users_count }}</span>
            <span style="width: 18px;">
                @if ($office->type !== 'section')
                    <button class="btn btn-sm btn-link p-0 btn-add-unit" data-type="{{ $nextType }}" data-parent="{{ $office->id }}" title="Add {{ $nextType }} under {{ $office->shortName() }}"><i class="fas fa-plus-circle text-success"></i></button>
                @endif
            </span>
            <button class="btn btn-sm btn-link p-0 btn-edit-office" data-id="{{ $office->id }}" title="Edit"><i class="fas fa-edit"></i></button>
            @if ($canDelete)
                <button class="btn btn-sm btn-link p-0 btn-delete-office" data-id="{{ $office->id }}" title="Delete"><i class="fas fa-trash-alt text-danger"></i></button>
            @endif
        </div>
    </div>
    @foreach ($children as $child)
        @include('BackEnd.offices.extras.office_node', ['office' => $child, 'depth' => $depth + 1])
    @endforeach
</div>
