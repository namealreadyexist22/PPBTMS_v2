<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\Setting;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;

class AvatarController extends Controller
{
    public function show(User $user): Response
    {
        if (! $user->avatar_data) {
            return $this->default();
        }

        return response(base64_decode($user->avatar_data))
            ->header('Content-Type', $user->avatar_mime ?: 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function default(): Response
    {
        $data = Setting::get('default_avatar_data');
        $mime = Setting::get('default_avatar_mime', 'image/png');

        if (! $data) {
            abort(404);
        }

        return response(base64_decode($data))
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=86400');
    }
}