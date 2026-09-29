<?php

use App\Core\Http\Controllers\LogController;
use App\Core\Http\Controllers\MenuController;
use App\Core\Http\Controllers\NotificationController;
use App\Core\Http\Controllers\RoleController;
use App\Core\Http\Controllers\SettingsController;
use App\Core\Http\Controllers\UserAccessController;
use App\Core\Http\Controllers\UserController;
use App\Core\Http\Controllers\AvatarController;
use App\Http\Controllers\BackEnd\LoginController;
use App\Http\Controllers\BackEnd\MainController;
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
