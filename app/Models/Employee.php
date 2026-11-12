<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\Auditable;

class Employee extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'registration', 'email', 'job_title', 'department_id', 'location_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'terminated_at' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

        public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

        /** Equipamentos que estão com o colaborador agora */
    public function openAssetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->whereNull('checked_in_at');
    }

    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    /** Licenças que o colaborador usa agora */
    public function activeLicenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class)->whereNull('revoked_at');
    }
    
}