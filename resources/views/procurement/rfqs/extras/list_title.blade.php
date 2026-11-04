{{-- RFQ list: its title, or (none given) its items. Expects $row. --}}
<div class="text-truncate {{ filled($row->title) ? '' : 'text-muted' }}" style="max-width: 320px;" title="{{ $row->label() }}">{{ $row->label() }}</div>
