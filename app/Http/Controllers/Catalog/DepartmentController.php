<?php

namespace App\Http\Controllers\Catalog;

use App\Models\Department;

class DepartmentController extends CatalogController
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function labels(): array
    {
        return [
            'route' => 'departments',
            'singular' => 'Setor',
            'plural' => 'Setores',
            'new' => 'Novo setor',
            'feminine' => false,
            'hint' => 'Exemplos: Engenharia, Financeiro, Compras, Tecnologia da Informação.',
            'empty' => 'Nenhum setor cadastrado ainda.',
        ];
    }
}