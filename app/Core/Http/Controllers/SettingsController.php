<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\Setting;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', [
            'appName' => Setting::get('app_name', config('app.name')),
            'appVersion' => Setting::get('app_version', '1.0.0'),
            'appLogo' => Setting::get('app_logo'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'app_version' => ['required', 'string', 'max:20'],
            'app_logo' => ['nullable', 'image', 'max:2048'],
        ]);

        Setting::set('app_name', $request->app_name);
        Setting::set('app_version', $request->app_version);

        if ($request->hasFile('app_logo')) {
            $old = Setting::get('app_logo');
            if ($old) {
                Storage::disk('public')->delete($old);
            }

            $path = $request->file('app_logo')->store('branding', 'public');
            Setting::set('app_logo', $path);
        }

        activity()
            ->causedBy(auth()->user())
            ->log('updated app settings');

        return back()->with('success', 'Settings updated.');
    }
}