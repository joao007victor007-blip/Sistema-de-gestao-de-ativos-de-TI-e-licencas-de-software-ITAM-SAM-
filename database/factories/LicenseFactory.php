<?php

namespace Database\Factories;

use App\Enums\LicenseAssignmentType;
use App\Enums\LicenseType;
use App\Models\Software;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'software_id' => fn () => Software::firstOrCreate(['name' => 'Microsoft 365'])->id,
            'name' => 'Microsoft 365 Business Standard',
            'license_type' => LicenseType::Assinatura,
            'assignment_type' => LicenseAssignmentType::PerUser,
            'seats' => 10,
            'expires_at' => now()->addYear(),
            'alert_days' => 30,
        ];
    }
}