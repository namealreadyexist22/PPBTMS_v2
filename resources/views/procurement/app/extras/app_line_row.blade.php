<tr>
    @if ($canEdit)
        <td class="ps-4"><input type="checkbox" class="form-check-input line-check" value="{{ $line->id }}"></td>
    @endif
    <td class="{{ $canEdit ? '' : 'ps-4' }}">
        <div class="fw-semibold">{{ $line->project_title }}</div>
        @if ($line->ppmpItems->count() > 1)
            <div class="text-muted">
                @foreach ($line->ppmpItems as $ppmpItem)
                    <div>• {{ $ppmpItem->ppmp->office->shortName() }}: {{ $ppmpItem->description }} ({{ number_format((float) $ppmpItem->estimated_budget, 2) }})</div>
                @endforeach
            </div>
        @endif
        @if ($line->remarks)<div class="text-muted"><i class="fas fa-comment-alt me-1"></i>{{ $line->remarks }}</div>@endif
    </td>
    <td style="max-width: 200px;">{{ $line->end_user }}</td>
    <td title="{{ $line->procurementMode->name }}">{{ $line->procurementMode->code }}</td>
    <td class="text-center">{{ $line->early_procurement ? 'Yes' : 'No' }}</td>
    <td>{{ $line->bid_criteria }}</td>
    <td class="text-nowrap">{{ $line->proc_start->format('M Y') }} – {{ $line->proc_end->format('M Y') }}</td>
    <td>{{ $line->fundSource->code }}</td>
    <td class="text-end text-nowrap fw-semibold">{{ number_format((float) $line->estimated_budget, 2) }}</td>
    <td class="text-center">{{ $line->ppmpItems->count() }}</td>
    @if ($canEdit)
        <td class="pe-4 text-nowrap">
            <button class="btn btn-sm btn-link p-0 me-2 btn-edit-line" data-id="{{ $line->id }}" title="Edit"><i class="fas fa-edit"></i></button>
            @if ($line->ppmpItems->count() > 1)
                <button class="btn btn-sm btn-link p-0 me-2 btn-ungroup" data-id="{{ $line->id }}" title="Split into one line per PPMP project"><i class="fas fa-object-ungroup"></i></button>
            @endif
            <button class="btn btn-sm btn-link p-0 text-danger btn-remove-line" data-id="{{ $line->id }}" title="Remove"><i class="fas fa-trash-alt"></i></button>
        </td>
    @endif
</tr>
