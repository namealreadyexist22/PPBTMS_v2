<?php

use App\Core\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Get an app setting by key, with an optional fallback if it's not set.
     * Usage in Blade: {{ setting('app_name', config('app.name')) }}
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}