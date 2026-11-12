<?php

namespace Database\Factories;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Obra ' . fake()->unique()->streetName(),
            'type' => LocationType::Obra,
            'city' => fake()->city(),
            'state' => 'PE',
            'is_active' => true,
        ];
    }
}