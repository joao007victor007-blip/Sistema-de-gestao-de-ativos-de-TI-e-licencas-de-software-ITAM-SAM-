<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignment extends Model
{
    // Quem registrou e a situação do termo são definidos pelo serviço, nunca por formulário
    protected $fillable = ['employee_id', 'location_id', 'checked_out_at', 'checkout_condition', 'notes'];

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'date',
            'checked_in_at' => 'date',
            'term_signed' => 'boolean',
            'term_signed_at' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function isOpen(): bool
    {
        return $this->checked_in_at === null;
    }
}