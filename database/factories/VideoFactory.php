<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "title" => fake()->sentence(5),
            "description" => fake()->sentence(10),
            'privacy' => 'public',
        ];
    }

    public function private(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'privacy' => 'private',
            ];
        });
    }
}
