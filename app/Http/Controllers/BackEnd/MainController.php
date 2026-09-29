<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Services\BackEnd\MainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;     // <-- Fixes all auth/logout errors cleanly
use Illuminate\Support\Facades\DB;       // <-- Fixes DB query errors if used here
use Illuminate\Support\Facades\Hash;     // <-- Fixes Hash::check errors
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class MainController extends Controller
{

    public function __construct(protected MainService $mainService) {
        
    }

    public function main_home(){
        return view('BackEnd.content.main.home');
    }

    public function main_profile(Request $request)
    {
        // Use Auth facade explicitly to guarantee method definition visibility
        $user = Auth::user();

        if ($request->isMethod('get')) {
            $activeSessions = [];

            if (config('session.driver') === 'database') {
                $activeSessions = DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->orderBy('last_activity', 'desc')
                    ->get();
            }

            return view('BackEnd.auth.content.profile', compact('user', 'activeSessions'));
        }

        if ($request->isMethod('post')) {
            $action = $request->input('action_type');

            switch ($action) {
                case 'update_password':
                    $request->validate([
                        'current_password' => 'required',
                        'password'         => 'required|min:8|confirmed',
                    ]);

                    if (!Hash::check($request->current_password, $user->password)) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => 'Incorrect current password.'
                        ], 400);
                    }

                    $user->password = Hash::make($request->password);
                    $user->setRememberToken(Str::random(60));
                    $user->save();

                    // Flush session cleanly using Facade context signatures
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    $request->session()->flash('success', 'Password updated successfully.');

                    return response()->json([
                        'status'   => 'success',
                        'message'  => 'Password updated. Relogging...',
                        'redirect' => route('auth.login')
                    ], 200);

                case 'link_google':
                    // 1. Generate the safe OAuth redirection path to Google's consent screen
                    $targetUrl = Socialite::driver('google')->redirect()->getTargetUrl();

                    return response()->json([
                        'status'   => 'success',
                        'message'  => 'Redirecting to Google...',
                        'redirect' => $targetUrl // Send this back to the AJAX pipeline
                    ], 200);

                case 'toggle_google':
                    // 2. Clear out the saved unique identifier string via your Service layer or directly
                    $user->google_id = null;
                    $user->save();

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Google account disconnected successfully.'
                    ], 200);

                case 'terminate_session':
                    if (config('session.driver') !== 'database') {
                        return response()->json([
                            'status'  => 'error',
                            'message' => 'Session driver configuration must use DB tracking rules.'
                        ], 400);
                    }

                    $targetSessionId = $request->input('session_id');
                    $currentSessionId = $request->session()->getId();

                    if ($targetSessionId) {
                        DB::table('sessions')
                            ->where('user_id', $user->id)
                            ->where('id', $targetSessionId)
                            ->delete();

                        if ($targetSessionId === $currentSessionId) {
                            Auth::logout();
                            $request->session()->invalidate();
                            $request->session()->regenerateToken();

                            return response()->json([
                                'status'   => 'success',
                                'message'  => 'Your active session has been terminated. Redirecting...',
                                'redirect' => route('auth.login')
                            ], 200);
                        }

                        return response()->json([
                            'status'  => 'success',
                            'message' => 'The selected target device session has been successfully closed.'
                        ], 200);
                    }

                    DB::table('sessions')
                        ->where('user_id', $user->id)
                        ->where('id', '!=', $currentSessionId)
                        ->delete();

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'All other remote active browser sessions have been terminated successfully.'
                    ], 200);

                default:
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Invalid operational request context.'
                    ], 400);
            }
        }
    }
}
