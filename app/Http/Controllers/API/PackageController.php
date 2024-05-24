<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Services\PackageRetrievalService;

class PackageController extends Controller
{
    private PackageRetrievalService $packageRetrievalService;

    public function __construct(PackageRetrievalService $packageRetrievalService)
    {
        $this->packageRetrievalService = $packageRetrievalService;
    }

    public function getAllPackages()
    {
        return PackageResource::collection($this->packageRetrievalService->getAllPackages());
    }

    public function getPackage(string $id)
    {
        return new PackageResource($this->packageRetrievalService->getPackage($id));
    }
}
