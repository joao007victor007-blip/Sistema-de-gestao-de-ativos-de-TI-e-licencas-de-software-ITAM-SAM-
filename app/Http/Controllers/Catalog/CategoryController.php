<?php

namespace App\Http\Controllers\Catalog;

use App\Models\Category;

class CategoryController extends CatalogController
{
    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function labels(): array
    {
        return [
            'route' => 'categories',
            'singular' => 'Categoria',
            'plural' => 'Categorias de equipamento',
            'new' => 'Nova categoria',
            'feminine' => true,
            'hint' => 'Exemplos: Notebook, Desktop, Monitor, Celular, Linha móvel, Impressora.',
            'empty' => 'Nenhuma categoria cadastrada ainda.',
        ];
    }
}