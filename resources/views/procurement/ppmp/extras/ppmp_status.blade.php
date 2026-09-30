@php
    $colors = ['draft' => 'secondary', 'submitted' => 'primary', 'returned' => 'warning',
               'approved' => 'success', 'superseded' => 'dark'];
@endphp
<span class="badge bg-{{ $colors[$ppmp->status->value] ?? 'secondary' }}">{{ $ppmp->status->label() }}</span>