<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => fn () => Category::firstOrCreate(['name' => 'Notebook'])->id,
            'model' => 'Latitude 5440',
            'serial_number' => strtoupper(fake()->unique()->bothify('??######')),
            'status' => AssetStatus::Disponivel,
        ];
    }
}