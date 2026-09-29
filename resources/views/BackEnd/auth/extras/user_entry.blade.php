<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-labelledby="userEntryModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-light py-3 border-bottom border-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center" id="userEntryModalLabel">
                    @if(isset($user))
                        <i class="fas fa-user-edit text-warning me-2"></i> Update User Details
                    @else
                        <i class="fas fa-user-plus text-primary me-2"></i> Add New User
                    @endif
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form_user_entry" autocomplete="off" novalidate enctype="multipart/form-data">
                @csrf

                @if(isset($user))
                    <input type="hidden" name="id" value="{{ $user->id }}">
                @endif

                <div class="modal-body p-4">

                    <div id="modal_error_summary" class="alert alert-danger d-none py-2 px-3 small rounded mb-3 shadow-sm">
                        <i class="fas fa-exclamation-triangle me-1.5"></i> Please correct the highlighted errors below.
                    </div>

                    <div class="d-flex align-items-center justify-content-center border border-dashed rounded p-3 mb-4 bg-light bg-opacity-50 mx-auto" style="max-width: 420px;">
                        <div class="position-relative role-button me-3 group" onclick="document.getElementById('avatar_file_input').click();" style="cursor: pointer;">
                            @if(isset($user) && !empty($user->img_slug) && $user->img_slug !== 'avatar-default.png')
                                <img id="avatar_preview" src="{{ asset('storage/avatars/' . $user->img_slug) }}"
                                     alt="Preview" class="rounded-circle border border-2 border-white shadow-sm" style="width: 65px; height: 65px; object-fit: cover;">
                            @else
                                <div id="avatar_placeholder" class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center border border-2 border-white shadow-sm" style="width: 65px; height: 65px;">
                                    <i class="fas fa-camera fa-lg text-muted"></i>
                                </div>
                                <img id="avatar_preview" src="" alt="Preview" class="rounded-circle border border-2 border-white shadow-sm d-none" style="width: 65px; height: 65px; object-fit: cover;">
                            @endif
                            <span class="position-absolute bottom-0 end-0 bg-dark text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 20px; height: 20px; font-size: 0.65rem;">
                                <i class="fas fa-pen"></i>
                            </span>
                        </div>

                        <div class="flex-grow-1">
                            <label class="form-label small fw-bold text-dark mb-0" style="cursor: pointer;" onclick="document.getElementById('avatar_file_input').click();">Profile Picture Avatar</label>
                            <span class="d-block text-muted small mt-0">Click avatar node component to browse image files (JPEG, PNG, max 2MB).</span>
                            <input type="file" id="avatar_file_input" name="avatar" class="d-none" accept="image/jpeg,image/png,image/jpg">
                            <div class="text-danger small fw-medium mt-1 invalid-feedback" id="avatar_error_node"></div>
                        </div>
                    </div>

                    <small class="text-uppercase fw-bold text-secondary tracking-wider d-block mb-3" style="font-size: 0.75rem;">
                        <i class="fas fa-id-card me-1"></i> Personal Profile Information
                    </small>

                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label for="fname" class="form-label small fw-semibold text-muted mb-1">First Name</label>
                            <input type="text" class="form-control form-control-sm rounded" id="fname" name="fname" placeholder="e.g. John" value="{{ $user->fname ?? '' }}" required>
                            <div class="invalid-feedback small fw-medium"></div>
                        </div>
                        <div class="col-md-2">
                            <label for="minitial" class="form-label small fw-semibold text-muted mb-1">M.I.</label>
                            <input type="text" class="form-control form-control-sm text-center rounded" id="minitial" name="minitial" maxlength="1" placeholder="M" value="{{ $user->minitial ?? '' }}">
                            <div class="invalid-feedback small fw-medium"></div>
                        </div>
                        <div class="col-md-5">
                            <label for="lname" class="form-label small fw-semibold text-muted mb-1">Last Name</label>
                            <input type="text" class="form-control form-control-sm rounded" id="lname" name="lname" placeholder="e.g. Doe" value="{{ $user->lname ?? '' }}" required>
                            <div class="invalid-feedback small fw-medium"></div>
                        </div>
                    </div>

                    <small class="text-uppercase fw-bold text-secondary tracking-wider d-block mb-3" style="font-size: 0.75rem;">
                        <i class="fas fa-lock me-1"></i> Account Credentials
                    </small>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="username" class="form-label small fw-semibold text-muted mb-1">Username</label>
                            <div class="input-group input-group-sm has-validation">
                                <span class="input-group-text bg-light text-secondary"><i class="fas fa-at"></i></span>
                                <input type="text" class="form-control rounded-end" id="username" name="username" placeholder="johndoe_dev" value="{{ $user->username ?? '' }}" required>
                                <div class="invalid-feedback small fw-medium"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold text-muted mb-1">Email Address</label>
                            <div class="input-group input-group-sm has-validation">
                                <span class="input-group-text bg-light text-secondary"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control rounded-end" id="email" name="email" placeholder="john.doe@company.com" value="{{ $user->email ?? '' }}" required>
                                <div class="invalid-feedback small fw-medium"></div>
                            </div>
                        </div>

                        @if(!isset($user))
                        <div class="col-md-6">
                            <label for="password" class="form-label small fw-semibold text-muted mb-1">Password</label>
                            <input type="password" class="form-control form-control-sm rounded" id="password" name="password" placeholder="••••••••" required>
                            <div class="invalid-feedback small fw-medium"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label small fw-semibold text-muted mb-1">Confirm Password</label>
                            <input type="password" class="form-control form-control-sm rounded" id="password_confirmation" name="password_confirmation" placeholder="••••••••" required>
                            <div class="invalid-feedback small fw-medium"></div>
                        </div>
                        @endif
                    </div>

                    <small class="text-uppercase fw-bold text-secondary tracking-wider d-block mb-3" style="font-size: 0.75rem;">
                        <i class="fas fa-sliders-h me-1"></i> Governance & Access Configuration
                    </small>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="categories" class="form-label small fw-semibold text-muted mb-1">User Category Assignment</label>
                            <select class="form-select form-select-sm rounded" id="categories" name="categories" required>
                                <option value="" disabled {{ !isset($user) ? 'selected' : '' }}>Choose a classification...</option>
                                <option value="1" {{ isset($user) && $user->categories == 1 ? 'selected' : '' }}>Category 1</option>
                                <option value="2" {{ isset($user) && $user->categories == 2 ? 'selected' : '' }}>Category 2</option>
                            </select>
                            <div class="invalid-feedback small fw-medium"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label small fw-semibold text-muted mb-1">System Security Role</label>
                            <select class="form-select form-select-sm rounded" id="role" name="role">
                                <option value="">— No role —</option>
                                @foreach (\Spatie\Permission\Models\Role::orderBy('name')->get() as $r)
                                    <option value="{{ $r->name }}" {{ isset($user) && $user->hasRole($r->name) ? 'selected' : '' }}>
                                        {{ $r->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback small fw-medium"></div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer bg-light py-2.5 border-top border-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-white border px-3 text-secondary fw-semibold rounded" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn_save_user" class="btn btn-sm {{ isset($user) ? 'btn-warning' : 'btn-primary' }} px-4 fw-semibold rounded shadow-sm">
                        <i class="fas {{ isset($user) ? 'fa-user-check' : 'fa-save' }} me-1.5"></i>
                        {{ isset($user) ? 'Apply Update' : 'Save User Record' }}
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
