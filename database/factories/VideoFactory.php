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
            'privacy' => 'public',
            'is_transcoded' => true,
            'is_approved' => true,
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

    public function untranscoded(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_transcoded' => false,
            ];
        });
    }

    public function unapproved(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_approved' => false,
            ];
        });
    }
}
