{{-- Standard Items list: edit / delete. Expects $item. --}}
<button class="btn btn-sm btn-link p-0 me-2 btn-item-entry" data-id="{{ $item->id }}" title="Edit"><i class="fas fa-edit"></i></button>
<button class="btn btn-sm btn-link p-0 text-danger btn-item-delete" data-id="{{ $item->id }}" title="Delete"><i class="fas fa-trash-alt"></i></button>
