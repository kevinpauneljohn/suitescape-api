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
            'firstname' => 'Admin',
            'lastname' => 'Suitescape',
            'email' => 'admin@gmail.com',
            'date_of_birth' => '2003-06-29',
            'password' => bcrypt('12'),
        ])->assignRole('super-admin');

        User::factory()->create([
            'email' => 'host@gmail.com',
            'password' => bcrypt('12'),
        ])->assignRole('host');

        User::factory()->create([
            'email' => 'guest@gmail.com',
            'password' => bcrypt('12'),
        ])->assignRole('guest');

        User::factory()->count(10)->create();
    }
}
