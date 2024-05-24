<?php

namespace App\Services;

use App\Models\Constant;

class ConstantService
{
    public function getAllConstants()
    {
        return Constant::all();
    }

    public function getConstant(string $key)
    {
        return Constant::where('key', $key)->firstOrFail();
    }

    public function updateConstant(string $key, string $value)
    {
        $setting = Constant::where('key', $key)->firstOrFail();
        $setting->update(['value' => $value]);
    }
}
