<?php

namespace App\Services;

class FileNameService
{
    public function generateFileName(): string
    {
        return auth('sanctum')->user()->email.'_'.date('d-m-Y-H-i-s').'_'.uniqid();
    }
}
