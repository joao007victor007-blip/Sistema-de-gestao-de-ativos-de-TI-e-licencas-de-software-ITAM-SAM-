<?php

namespace App\Http\Controllers\Catalog;

use App\Models\Software;

class SoftwareController extends CatalogController
{
    protected function modelClass(): string
    {
        return Software::class;
    }

    protected function labels(): array
    {
        return [
            'route' => 'software',
            'singular' => 'Software',
            'plural' => 'Softwares',
            'new' => 'Novo software',
            'feminine' => false,
            'hint' => 'Exemplos: Microsoft 365, AutoCAD, Revit, Windows 11 Pro, Antivírus Bitdefender.',
            'empty' => 'Nenhum software cadastrado ainda.',
        ];
    }
}