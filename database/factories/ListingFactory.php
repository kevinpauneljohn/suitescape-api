<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkInTime = $this->faker->dateTime();
        $checkOutTime = Carbon::parse($checkInTime)->addHours(rand(1, 12));

        $isEntirePlace = $this->faker->boolean();

        return [
            'user_id' => User::all()->random()->id,
            'name' => ucwords(fake()->words(3, true)),
            'location' => fake()->streetAddress().', '.fake()->city().', '.fake()->stateAbbr().' '.fake()->postcode(),
            'description' => fake()->paragraphs(3, true),
            'check_in_time' => $checkInTime->format('H:i'),
            'check_out_time' => $checkOutTime->format('H:i'),
            'adult_capacity' => fake()->numberBetween(1, 10),
            'child_capacity' => fake()->numberBetween(0, 5),
            'facility_type' => fake()->randomElement(['house', 'hotel', 'apartment', 'condominium', 'cabin',  'villa']),
            'is_pet_friendly' => fake()->boolean(),
            'parking_lot' => fake()->boolean(),
            'is_entire_place' => $isEntirePlace,
            'entire_place_price' => $isEntirePlace ? fake()->randomFloat(2, 1000, 10000) : null,
        ];
    }

    public function mine(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'user_id' => User::first()->id,
            ];
        });
    }
}
