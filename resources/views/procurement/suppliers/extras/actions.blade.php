{{-- Suppliers list: edit / delete. Expects $supplier. --}}
<button class="btn btn-sm btn-link p-0 me-2 btn-supplier-entry" data-id="{{ $supplier->id }}" title="Edit"><i class="fas fa-edit"></i></button>
<button class="btn btn-sm btn-link p-0 text-danger btn-supplier-delete" data-id="{{ $supplier->id }}" title="Delete"><i class="fas fa-trash-alt"></i></button>
