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

    /**
     * Get all packages.
     *
     * Fetches and returns all packages as PackageResource collection.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllPackages()
    {
        return PackageResource::collection($this->packageRetrievalService->getAllPackages());
    }

    /**
     * Get a package by ID.
     *
     * Fetches a single package by ID and returns it as PackageResource.
     *
     * @param string $id
     * @return PackageResource
     */
    public function getPackage(string $id)
    {
        return new PackageResource($this->packageRetrievalService->getPackage($id));
    }
}
