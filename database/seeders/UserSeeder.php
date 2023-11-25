<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'firstname' => 'Mel Mathew',
            'middlename' => 'Cabana',
            'lastname' => 'Palana',
            'email' => 'melpalana13@gmail.com',
            'mobile_number' => '09275393573',
            'date_of_birth' => '2003-06-29',
            'password' => bcrypt('12'),
        ])->assignRole('super-admin');

        User::factory()->create([
            'email' => 'host@gmail.com',
            'password' => bcrypt('12'),
        ])->assignRole('host');

        User::factory()->count(10)->create();
    }
}
