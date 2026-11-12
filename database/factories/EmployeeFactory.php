<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'registration' => (string) fake()->unique()->numberBetween(1000, 99999),
            'email' => fake()->unique()->safeEmail(),
            'job_title' => 'Engenheiro Civil',
            'is_active' => true,
        ];
    }
}