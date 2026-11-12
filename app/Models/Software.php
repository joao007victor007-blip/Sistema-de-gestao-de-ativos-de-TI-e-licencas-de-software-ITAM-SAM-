<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Software extends CatalogModel
{
    protected $table = 'software';

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }
}