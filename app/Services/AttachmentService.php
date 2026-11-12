<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava e remove anexos. Os arquivos ficam em storage/app/private/attachments;
 * o banco guarda apenas a ficha de cada um.
 */
class AttachmentService
{
    public function store(Model $attachable, UploadedFile $file, string $category, User $by): Attachment
    {
        // Extensão deduzida pelo conteúdo do arquivo, e não pelo nome que veio do usuário
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs('attachments/' . now()->format('Y/m'), Str::uuid() . '.' . $extension, 'local');

        try {
            $attachment = new Attachment();
            $attachment->forceFill([
                'attachable_type' => $attachable::class,
                'attachable_id' => $attachable->getKey(),
                'category' => $category,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $by->id,
            ]);
            $attachment->save();
        } catch (Throwable $exception) {
            // Sem a ficha no banco, o arquivo ficaria órfão: remove antes de repassar o erro
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        Audit::record(
            AuditAction::AttachmentAdded,
            $attachable,
            'Anexo incluído em ' . AuditLog::entityName($attachable::class) . ' ' . $attachable->auditLabel()
            . ": {$attachment->categoryLabel()} ({$attachment->original_name})",
        );

        return $attachment;
    }

    public function delete(Attachment $attachment): void
    {
        $attachable = $attachment->attachable;

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        if ($attachable) {
            Audit::record(
                AuditAction::AttachmentRemoved,
                $attachable,
                'Anexo removido de ' . AuditLog::entityName($attachable::class) . ' ' . $attachable->auditLabel()
                . ": {$attachment->original_name}",
            );
        }
    }
}