<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::all()->random()->id,
            'coupon_id' => Coupon::all()->random()->id,
            'amount' => fake()->numberBetween(1000, 10000),
            'status' => ['upcoming', 'ongoing', 'cancelled', 'completed'][rand(0, 3)],
        ];
    }
}
