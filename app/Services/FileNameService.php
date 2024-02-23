<?php

namespace App\Services;

class FileNameService
{
    public function generateUniqueName(): string
    {
        return auth('sanctum')->user()->email.'_'.date('d-m-Y-H-i-s').'_'.uniqid();
    }

    public function generateFileName($extension): string
    {
        return $this->generateUniqueName().'.'.$extension;
    }
}
