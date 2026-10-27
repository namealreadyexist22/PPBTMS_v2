import './bootstrap';
import jQuery from 'jquery';
import * as bootstrap from 'bootstrap';
import toastr from 'toastr';
import Swal from 'sweetalert2'; // <-- 1. ADD THIS IMPORT
import 'admin-lte';

// Assign to window for global access
window.$ = window.jQuery = jQuery;
window.bootstrap = bootstrap;
window.toastr = toastr;
window.Swal = Swal;

// Select2: searchable dropdowns ($('select').select2({ theme: 'bootstrap-5' }))
import select2 from 'select2';
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';
select2(window, jQuery);

// 2. Explicit DataTables Sequence
import DataTable from 'datatables.net-bs5';
window.DataTable = DataTable;

import 'datatables.net-buttons-bs5';
import 'datatables.net-select-bs5';

import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';

// 3. Configure Toastr
toastr.options = {
    "closeButton": true,
    "progressBar": true,
    "positionClass": "toast-top-right",
    "timeOut": "4000",
    "extendedTimeOut": "1000"
};

// 4. Session expired while the page was open: any background request (jQuery, DataTables, fetch) answered
//    401 (not logged in) or 419 (page expired) sends the user to the login page, once
let sessionExpired = false;
function onSessionExpired() {
    if (sessionExpired) return;
    sessionExpired = true;
    const login = document.querySelector('meta[name="login-url"]')?.content || '/';
    toastr.warning('Your session has expired. Please log in again.', 'Session expired', { timeOut: 2500 });
    setTimeout(() => { window.location.href = login; }, 1500);
}
jQuery(document).ajaxError((event, xhr) => {
    if (xhr.status === 401 || xhr.status === 419) onSessionExpired();
});
const nativeFetch = window.fetch.bind(window);
window.fetch = (...args) => nativeFetch(...args).then((response) => {
    if (response.status === 401 || response.status === 419) onSessionExpired();
    return response;
});

// 5. Modern Bootstrap Dropdown handling
document.addEventListener('DOMContentLoaded', () => {
    const dropdownElementList = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    [...dropdownElementList].map(dropdownToggleEl => new bootstrap.Dropdown(dropdownToggleEl));
});
