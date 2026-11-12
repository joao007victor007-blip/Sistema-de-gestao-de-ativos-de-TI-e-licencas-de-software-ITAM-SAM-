<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Attachment;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'model' => $this->filled('model') ? trim((string) $this->input('model')) : null,
            'asset_tag' => $this->filled('asset_tag') ? mb_strtoupper(trim((string) $this->input('asset_tag'))) : null,
            'serial_number' => $this->filled('serial_number') ? mb_strtoupper(trim((string) $this->input('serial_number'))) : null,
        ]);
    }

    public function rules(): array
    {
        $asset = $this->route('asset');

        // "Em uso" e "Descartado" só mudam pelas ações próprias (entrega/devolução e descarte).
        // Se o ativo já está numa dessas situações, o formulário só pode manter a situação atual.
        $allowedStatuses = array_map(fn (AssetStatus $status) => $status->value, AssetStatus::editable());

        if ($asset && ! $asset->status->isEditable()) {
            $allowedStatuses = [$asset->status->value];
        }

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'manufacturer_id' => ['nullable', 'integer', Rule::exists('manufacturers', 'id')],
            'model' => ['nullable', 'string', 'max:120'],
            'asset_tag' => ['nullable', 'string', 'max:50', Rule::unique('assets', 'asset_tag')->ignore($asset)],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('assets', 'serial_number')->ignore($asset)],
            'description' => ['nullable', 'string', 'max:2000'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'status' => ['required', Rule::in($allowedStatuses)],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'warranty_until' => array_filter([
                'nullable',
                'date',
                $this->filled('purchase_date') ? 'after_or_equal:purchase_date' : null,
            ]),
            'notes' => ['nullable', 'string', 'max:2000'],
            'invoice_file' => ['nullable', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'max:' . Attachment::MAX_KILOBYTES],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'categoria',
            'manufacturer_id' => 'fabricante',
            'model' => 'modelo',
            'asset_tag' => 'número de patrimônio',
            'serial_number' => 'número de série',
            'description' => 'descrição',
            'location_id' => 'local',
            'status' => 'situação',
            'purchase_date' => 'data de compra',
            'purchase_price' => 'valor de compra',
            'invoice_number' => 'nota fiscal',
            'supplier' => 'fornecedor',
            'warranty_until' => 'fim da garantia',
            'notes' => 'observações',
            'invoice_file' => 'arquivo da nota fiscal',
        ];
    }
}