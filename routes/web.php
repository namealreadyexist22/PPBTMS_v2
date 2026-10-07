<?php

use App\Core\Http\Controllers\AvatarController;
use App\Core\Http\Controllers\LogController;
use App\Core\Http\Controllers\LookupController;
use App\Core\Http\Controllers\MenuController;
use App\Core\Http\Controllers\NotificationController;
use App\Core\Http\Controllers\OfficeController;
use App\Core\Http\Controllers\RoleController;
use App\Core\Http\Controllers\SettingsController;
use App\Core\Http\Controllers\UserAccessController;
use App\Core\Http\Controllers\UserController;
use App\Http\Controllers\BackEnd\LoginController;
use App\Http\Controllers\BackEnd\MainController;
use App\Http\Controllers\Procurement\AppController;
use App\Http\Controllers\Procurement\BudgetAllocationController;
use App\Http\Controllers\Procurement\StandardItemController;
use App\Http\Controllers\Procurement\DivisionPpmpController;
use App\Http\Controllers\Procurement\PpmpController;
use Illuminate\Support\Facades\Route;

    Route::get('/', function () {
        return redirect()->route('auth.login');
    });

    Route::group(['prefix' => 'auth', 'as' => 'auth.'], function() {
        // Add ->middleware('guest') to these two routes
        Route::get('/login', [LoginController::class, 'showLoginForm'])
            ->middleware('portal.guest')
            ->name('login');

        Route::post('/login', [LoginController::class, 'login'])
            ->middleware('portal.guest')
            ->name('attempt');

        Route::post('/google', [LoginController::class, 'redirectToGoogle'])
            ->name('redirect');

        Route::get('/google/callback', [LoginController::class, 'handleGoogleCallback'])
            ->name('callback');

        // Do NOT add guest middleware to logout, otherwise logged-in users can't log out!
        Route::post('/logout', [LoginController::class, 'logout'])
            ->name('logout');
    });

    Route::prefix('app')
        ->as('app.')
        ->middleware(['portal.auth']) // 'web' is already applied automatically in web.php
    ->group(function () {

        Route::get('main/home', [MainController::class, 'main_home'])->name('main.home');
        Route::match(['get','post'],'main/profile', [MainController::class, 'main_profile'])->name('main.profile');

        Route::get('users/{user}/avatar', [AvatarController::class, 'show'])->name('users.avatar');
        Route::get('users/avatar/default', [AvatarController::class, 'default'])->name('users.avatar.default');

    });


    Route::prefix('core')
        ->as('core.')
        ->middleware(['portal.auth'])
    ->group(function () {

        Route::middleware('perm:manage users')->group(function () {
            Route::get('users', [UserController::class, 'main_user'])->name('users.index');
            Route::get('users/entry', [UserController::class, 'user_entry'])->name('users.entry');
            Route::post('users/store', [UserController::class, 'user_store'])->name('users.store');


            Route::get('users/cpass', [UserController::class, 'user_cpass'])->name('users.cpass');
            Route::post('users/upass', [UserController::class, 'user_upass'])->name('users.upass');
            Route::post('users/ustat', [UserController::class, 'user_ustat'])->name('users.ustat');
            
            Route::delete('users/destroy', [UserController::class, 'user_destroy'])->name('users.destroy')->middleware('perm:menu.manage-users-destroy');
        });
            
        Route::middleware('perm:manage access')->group(function () {
            Route::get('access', [UserAccessController::class, 'index'])->name('access.index');
            Route::put('users/{user}/access', [UserAccessController::class, 'update'])->name('access.update');
        });

        Route::middleware('perm:manage lookups')->group(function () {
            Route::get('lookups', [LookupController::class, 'index'])->name('lookups.index');
            Route::post('lookups/store', [LookupController::class, 'store'])->name('lookups.store');
            Route::delete('lookups/destroy', [LookupController::class, 'destroy'])->name('lookups.destroy');
        });

        Route::middleware('perm:manage offices')->group(function () {
            Route::get('offices', [OfficeController::class, 'index'])->name('offices.index');
            Route::get('offices/entry', [OfficeController::class, 'entry'])->name('offices.entry');
            Route::post('offices/store', [OfficeController::class, 'store'])->name('offices.store');

            Route::delete('offices/destroy', [OfficeController::class, 'destroy'])->name('offices.destroy')->middleware('perm:menu.offices-destroy');
        });

        Route::middleware('perm:manage menus')->group(function () {
            Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
            Route::get('menus/data', [MenuController::class, 'data'])->name('menus.data');
            Route::get('menus/entry', [MenuController::class, 'entry'])->name('menus.entry');
            Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
            Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
            Route::get('menus/{menu}/submenus', [MenuController::class, 'submenus'])->name('menus.submenus');
            
            Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy')->middleware('perm:menu.menu-management-destroy');
        });

        Route::middleware('perm:manage roles')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
            Route::get('roles/entry', [RoleController::class, 'entry'])->name('roles.entry');
            Route::get('roles/{role}/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');

            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('perm:menu.roles-permissions-destroy');
        });

        Route::middleware('perm:manage logs')->group(function () {
            Route::get('logs', [LogController::class, 'index'])->name('logs.index');
            Route::delete('logs', [LogController::class, 'clear'])->name('logs.clear')->middleware('perm:menu.activity-logs-clear');
        });

        Route::middleware('perm:manage settings')->group(function () {
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    });

    Route::prefix('procurement')
        ->as('procurement.')
        ->middleware(['portal.auth'])
    ->group(function () {

        // Budget allocation per office (Budget officer)
        Route::middleware('perm:manage budget')->group(function () {
            Route::get('budget', [BudgetAllocationController::class, 'index'])->name('budget.index');
            Route::post('budget/store', [BudgetAllocationController::class, 'store'])->name('budget.store');
        });

        // Standard items (articles with standard cost and TWG specifications)
        Route::middleware('perm:manage items')->group(function () {
            Route::get('items', [StandardItemController::class, 'index'])->name('items.index');
            Route::get('items/entry', [StandardItemController::class, 'entry'])->name('items.entry');
            Route::post('items/store', [StandardItemController::class, 'store'])->name('items.store');
            Route::delete('items/destroy', [StandardItemController::class, 'destroy'])->name('items.destroy');
        });

        // Annual Procurement Plan (per fiscal year and region)
        Route::middleware('perm:manage app')->group(function () {
            Route::get('app', [AppController::class, 'index'])->name('app.index');
            Route::post('app/store', [AppController::class, 'store'])->name('app.store');
            Route::post('app/signatories', [AppController::class, 'signatories'])->name('app.signatories');
            Route::get('app/{app}', [AppController::class, 'show'])->name('app.show');
            Route::get('app/{app}/print', [AppController::class, 'print'])->name('app.print');
            Route::post('app/{app}/generate', [AppController::class, 'generate'])->name('app.generate');
            Route::post('app/{app}/group', [AppController::class, 'group'])->name('app.group');
            Route::post('app/{app}/ungroup', [AppController::class, 'ungroup'])->name('app.ungroup');
            Route::get('app/{app}/lines/entry', [AppController::class, 'lineEntry'])->name('app.lines.entry');
            Route::post('app/{app}/lines/store', [AppController::class, 'lineStore'])->name('app.lines.store');
            Route::delete('app/{app}/lines/destroy', [AppController::class, 'lineDestroy'])->name('app.lines.destroy');
            Route::post('app/{app}/submit', [AppController::class, 'submit'])->name('app.submit');
            Route::post('app/{app}/recommend', [AppController::class, 'recommend'])->name('app.recommend');
            Route::post('app/{app}/approve', [AppController::class, 'approve'])->name('app.approve');
            Route::post('app/{app}/return', [AppController::class, 'returnToSecretariat'])->name('app.return');
            Route::post('app/{app}/update-version', [AppController::class, 'createUpdated'])->name('app.update-version');
        });

        Route::middleware('perm:manage ppmp')->group(function () {
            Route::get('ppmp', [PpmpController::class, 'index'])->name('ppmp.index');
            Route::get('ppmp/entry', [PpmpController::class, 'entry'])->name('ppmp.entry');
            Route::post('ppmp/store', [PpmpController::class, 'store'])->name('ppmp.store');

            // Details page and its actions ({ppmp} is the PPMP's uuid)
            Route::get('ppmp/{ppmp}', [PpmpController::class, 'show'])->name('ppmp.show');
            Route::get('ppmp/{ppmp}/print', [PpmpController::class, 'print'])->name('ppmp.print');
            Route::get('ppmp/{ppmp}/items/entry', [PpmpController::class, 'itemEntry'])->name('ppmp.items.entry');
            Route::post('ppmp/{ppmp}/items/store', [PpmpController::class, 'itemStore'])->name('ppmp.items.store');
            Route::delete('ppmp/{ppmp}/items/destroy', [PpmpController::class, 'itemDestroy'])->name('ppmp.items.destroy');
            Route::get('ppmp/{ppmp}/items/{item}/market-scoping', [PpmpController::class, 'marketScopingPrint'])->name('ppmp.items.market-scoping');
            Route::get('ppmp/{ppmp}/market-scoping', [PpmpController::class, 'marketScopingPrintAll'])->name('ppmp.market-scoping');
            Route::get('ppmp/{ppmp}/items/{item}/distribution', [PpmpController::class, 'distributionEntry'])->name('ppmp.items.distribution');
            Route::post('ppmp/{ppmp}/items/{item}/distribution', [PpmpController::class, 'distributionStore'])->name('ppmp.items.distribution.store');
            Route::get('ppmp/{ppmp}/attachments/{attachment}', [PpmpController::class, 'attachmentDownload'])->name('ppmp.attachments.show');
            Route::delete('ppmp/{ppmp}/attachments/destroy', [PpmpController::class, 'attachmentDestroy'])->name('ppmp.attachments.destroy');
            Route::get('ppmp/{ppmp}/paps/entry', [PpmpController::class, 'papEntry'])->name('ppmp.paps.entry');
            Route::post('ppmp/{ppmp}/paps/store', [PpmpController::class, 'papStore'])->name('ppmp.paps.store');
            Route::delete('ppmp/{ppmp}/paps/destroy', [PpmpController::class, 'papDestroy'])->name('ppmp.paps.destroy');
            Route::post('ppmp/{ppmp}/submit', [PpmpController::class, 'submit'])->name('ppmp.submit');
            Route::post('ppmp/{ppmp}/return', [PpmpController::class, 'returnToOffice'])->name('ppmp.return');
            Route::post('ppmp/{ppmp}/amend', [PpmpController::class, 'amend'])->name('ppmp.amend');

            // Division PPMP: sections combined, approved by the division head -> PPMP No. 1, 2, 3...
            Route::get('division-ppmp', [DivisionPpmpController::class, 'index'])->name('division-ppmp.index');
            Route::post('division-ppmp/approve', [DivisionPpmpController::class, 'approve'])->name('division-ppmp.approve');
            Route::get('division-ppmp/preview/{office}', [DivisionPpmpController::class, 'preview'])->name('division-ppmp.preview');
            Route::get('division-ppmp/{divisionPpmp}', [DivisionPpmpController::class, 'show'])->name('division-ppmp.show');
            Route::get('division-ppmp/{divisionPpmp}/print', [DivisionPpmpController::class, 'print'])->name('division-ppmp.print');

            Route::delete('ppmp/destroy', [PpmpController::class, 'destroy'])
                ->name('ppmp.destroy')->middleware('perm:menu.ppmp-destroy');
        });

    });