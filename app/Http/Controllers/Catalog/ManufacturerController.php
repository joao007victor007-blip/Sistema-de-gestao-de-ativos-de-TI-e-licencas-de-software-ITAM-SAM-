<?php

namespace App\Http\Controllers\Catalog;

use App\Models\Manufacturer;

class ManufacturerController extends CatalogController
{
    protected function modelClass(): string
    {
        return Manufacturer::class;
    }

    protected function labels(): array
    {
        return [
            'route' => 'manufacturers',
            'singular' => 'Fabricante',
            'plural' => 'Fabricantes',
            'new' => 'Novo fabricante',
            'feminine' => false,
            'hint' => 'Exemplos: Dell, Lenovo, Samsung, Microsoft, Autodesk. Use as observações para site e contato de suporte.',
            'empty' => 'Nenhum fabricante cadastrado ainda.',
        ];
    }
}