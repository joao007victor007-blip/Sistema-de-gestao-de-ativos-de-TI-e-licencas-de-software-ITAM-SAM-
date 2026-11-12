<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

/**
 * Base dos cadastros simples (setores, categorias, fabricantes):
 * nome único, observações e situação ativo/desativado.
 */
abstract class CatalogModel extends Model
{
        use Auditable;

    protected $fillable = ['name', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}