<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends CatalogModel
{
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}