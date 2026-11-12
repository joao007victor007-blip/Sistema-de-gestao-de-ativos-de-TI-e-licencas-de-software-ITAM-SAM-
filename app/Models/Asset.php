<?php

namespace App\Models;

use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Asset extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'asset_tag', 'category_id', 'manufacturer_id', 'model', 'serial_number', 'description',
        'location_id', 'status', 'purchase_date', 'purchase_price', 'invoice_number', 'supplier',
        'warranty_until', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'purchase_date' => 'date',
            'warranty_until' => 'date',
            'disposed_at' => 'date',
            'purchase_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Gera o código interno (ATV-000123) logo após o cadastro, a partir do id
        static::created(function (Asset $asset) {
            $asset->code = sprintf('ATV-%06d', $asset->id);
            $asset->saveQuietly();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Todo o histórico de entregas e devoluções */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /** A entrega em aberto (com quem o equipamento está agora), se houver */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('checked_in_at');
    }

        /** Licenças atribuídas a este equipamento (inclusive as já liberadas) */
    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

        /** Notas fiscais, termos e outros documentos */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /** Identificador exibido nas telas: o patrimônio, quando existir; senão, o código interno */
    public function displayIdentifier(): string
    {
        return $this->asset_tag ? 'Pat. ' . $this->asset_tag : $this->code;
    }

    /** Nome do equipamento: fabricante e modelo; sem eles, a categoria */
    public function displayName(): string
    {
        $name = trim(($this->manufacturer?->name ?? '') . ' ' . ($this->model ?? ''));

        return $name !== '' ? $name : $this->category->name;
    }

        /** Nas descrições da auditoria. Usa o id porque o código é gerado logo após o cadastro */
    public function auditLabel(): string
    {
        return $this->asset_tag ? 'Pat. ' . $this->asset_tag : sprintf('ATV-%06d', $this->id);
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_until !== null && $this->warranty_until->gte(today());
    }
}