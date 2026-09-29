<?php

namespace Database\Seeders;

use App\Core\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(['key' => 'app_name'], ['value' => 'Base Template']);
        Setting::firstOrCreate(['key' => 'app_version'], ['value' => '1.0.0']);
        Setting::firstOrCreate(['key' => 'app_logo'], ['value' => null]);
        
        if (! Setting::where('key', 'default_avatar_data')->exists()) {
            $path = public_path('assets/img/userlogo.png');

            if (file_exists($path)) {
                Setting::create(['key' => 'default_avatar_data', 'value' => base64_encode(file_get_contents($path))]);
                Setting::create(['key' => 'default_avatar_mime', 'value' => 'image/png']);
            }
        }
    }

    
}