{{-- Over a server-side list's table while it loads: shown while its card has .list-busy (set from the table's processing.dt).
     Goes inside a .position-relative wrapper around the table only, so the search box and filters stay usable. --}}
<div class="list-loading" aria-hidden="true">
    <div class="d-flex align-items-center gap-2 bg-white border rounded-pill shadow-sm px-3 py-2 small text-muted">
        <span class="spinner-border spinner-border-sm text-primary" role="status"></span> Loading…
    </div>
</div>
