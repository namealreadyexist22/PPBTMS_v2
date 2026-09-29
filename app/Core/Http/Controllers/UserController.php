<?php

namespace App\Core\Http\Controllers;

use App\Core\DataTables\UsersDataTable;
use App\Http\Controllers\Controller;
use App\Core\Http\Requests\StoreUserRequest;
use App\Core\Http\Requests\UpdatePasswordRequest;
use App\Core\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{

    public function __construct(
        protected UserService $userService
    ) {}

    public function main_user(UsersDataTable $dataTable)
    {
        return $dataTable->render('BackEnd.auth.content.user');
    }

    public function user_entry(Request $request)
    {
        return $this->userService->main_user($request);
    }

    public function user_store(StoreUserRequest $request)
    {
        return $this->userService->user_store($request);
    }

    public function user_cpass(Request $request)
    {
        return $this->userService->user_cpass($request);
    }

    public function user_upass(UpdatePasswordRequest $request)
    {
        return $this->userService->user_upass($request);
    }

    public function user_ustat(Request $request)
    {
        return $this->userService->user_ustat($request);
    }
    public function user_destroy(Request $request)
    {
        return $this->userService->user_destroy($request);
    }
}
