<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends CatalogModel
{
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}