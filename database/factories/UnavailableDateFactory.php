<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnavailableDate>
 */
class UnavailableDateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 year');
        $endDate = Carbon::parse($startDate)->addDays(rand(1, 7));

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
