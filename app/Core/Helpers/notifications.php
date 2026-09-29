<?php

use App\Core\Notifications\SystemNotification;
use Illuminate\Contracts\Auth\Authenticatable;

if (! function_exists('notify_user')) {
    /**
     * Send a database notification to a user. Usage anywhere in the app:
     *   notify_user($user, 'Role changed', 'You are now an Editor.', route('...'));
     */
    function notify_user(Authenticatable $user, string $title, string $message, ?string $url = null, string $icon = 'fas fa-bell'): void
    {
        $user->notify(new SystemNotification($title, $message, $url, $icon));
    }
}