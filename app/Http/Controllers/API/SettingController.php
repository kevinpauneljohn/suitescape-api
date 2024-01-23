<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingUpdateRequest;
use App\Http\Resources\SettingResource;
use App\Services\SettingsService;
use Spatie\Permission\Exceptions\UnauthorizedException;

class SettingController extends Controller
{
    private SettingsService $settingService;

    public function __construct(SettingsService $settingService)
    {
        $this->middleware('auth:sanctum')->only('updateSetting');

        $this->settingService = $settingService;
    }

    public function getAllSettings()
    {
        return SettingResource::collection($this->settingService->getAllSettings());
    }

    public function getSetting(string $key)
    {
        return new SettingResource($this->settingService->getSetting($key));
    }

    public function updateSetting(string $key, SettingUpdateRequest $request)
    {
        $validated = $request->validated();

        if (auth()->user()->cannot('update setting')) {
            throw new UnauthorizedException(403);
        }

        $this->settingService->updateSetting($key, $validated['value']);

        return response()->json([
            'message' => 'Settings updated successfully.',
        ]);
    }
}
