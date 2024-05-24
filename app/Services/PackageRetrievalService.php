<?php

namespace App\Services;

use App\Models\Package;

class PackageRetrievalService
{
    public function getAllPackages()
    {
        return Package::with('packageImages')->get();
    }

    public function getPackage(string $id)
    {
        return Package::with('packageImages')->findOrFail($id);
    }
}
