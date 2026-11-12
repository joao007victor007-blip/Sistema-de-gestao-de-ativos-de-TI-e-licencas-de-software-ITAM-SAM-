<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    public const MAX_KILOBYTES = 2048;
    public const MAX_PER_ITEM = 5;
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public const CATEGORIES = [
        'nota_fiscal' => 'Nota fiscal',
        'termo' => 'Termo de responsabilidade',
        'contrato' => 'Contrato',
        'outro' => 'Outro documento',
    ];

    // Nenhum campo preenchível por formulário: só o AttachmentService grava anexos
    protected $fillable = [];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Documento';
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1, ',', '.') . ' MB'
            : number_format(max(1, $this->size / 1024), 0, ',', '.') . ' KB';
    }
}