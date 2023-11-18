<?php

namespace App\Services;

use App\Models\Setting;
use Exception;

class SettingsService
{
    public function getAllSettings()
    {
        return Setting::all();
    }

    public function getSetting(string $key)
    {
        return Setting::where('key', $key)->firstOrFail();
    }

    public function updateSetting(string $key, string $value)
    {
        $setting = Setting::where('key', $key)->firstOrFail();
        $setting->update(['value' => $value]);
    }
}
