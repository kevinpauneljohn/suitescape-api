<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Violation;

class ViolationsTableSeeder extends Seeder
{
    public function run(): void
    {
        $violations = [
            'Copyright Infringement',
            'Misleading Content',
            'Offensive Material',
            'Privacy Violations',
            'Prohibited Activities',
            'Deceptive Advertising',
        ];

        foreach ($violations as $violation) {
            Violation::create([
                'name' => $violation,
            ]);
        }
    }
}
