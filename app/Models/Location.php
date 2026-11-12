<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\Auditable;

class Location extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'type', 'city', 'state', 'notes'];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
{
    return $this->hasMany(Employee::class);
}
}