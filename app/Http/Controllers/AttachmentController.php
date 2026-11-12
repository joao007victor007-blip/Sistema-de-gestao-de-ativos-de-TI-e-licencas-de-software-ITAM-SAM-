<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\License;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class AttachmentController extends Controller
{
    public function __construct(private AttachmentService $attachments)
    {
    }

    public function storeForAsset(Request $request, Asset $asset): RedirectResponse
    {
        return $this->store($request, $asset, route('assets.show', $asset));
    }

    public function storeForLicense(Request $request, License $license): RedirectResponse
    {
        return $this->store($request, $license, route('licenses.show', $license));
    }

    /** Abre o anexo no navegador (PDF e imagens são exibidos direto) */
    public function show(Attachment $attachment): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $fallbackName = preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($attachment->original_name)) ?: 'anexo';

        return response()->file(Storage::disk('local')->path($attachment->path), [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $attachment->original_name, $fallbackName),
        ]);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $redirect = $this->parentUrl($attachment);
        $this->attachments->delete($attachment);

        return redirect($redirect)->with('status', 'Anexo removido.');
    }

    private function store(Request $request, Model $attachable, string $redirect): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $extensions = implode(',', Attachment::EXTENSIONS);

        $data = $request->validate(
            [
                'arquivo' => ['required', 'file', "extensions:{$extensions}", "mimes:{$extensions}", 'max:' . Attachment::MAX_KILOBYTES],
                'categoria' => ['required', Rule::in(array_keys(Attachment::CATEGORIES))],
            ],
            [
                'arquivo.max' => 'O arquivo pode ter no máximo 2 MB.',
                'arquivo.extensions' => 'Envie um arquivo PDF, JPG ou PNG.',
                'arquivo.mimes' => 'Envie um arquivo PDF, JPG ou PNG.',
            ],
            ['arquivo' => 'arquivo', 'categoria' => 'tipo de documento'],
        );

        if ($attachable->attachments()->count() >= Attachment::MAX_PER_ITEM) {
            return redirect($redirect)->withErrors([
                'arquivo' => 'Limite de ' . Attachment::MAX_PER_ITEM . ' anexos por item atingido. Remova um anexo antes de incluir outro.',
            ]);
        }

        $this->attachments->store($attachable, $request->file('arquivo'), $data['categoria'], $request->user());

        return redirect($redirect)->with('status', 'Documento anexado.');
    }

    private function parentUrl(Attachment $attachment): string
    {
        return match ($attachment->attachable_type) {
            Asset::class => route('assets.show', $attachment->attachable_id),
            License::class => route('licenses.show', $attachment->attachable_id),
            default => route('dashboard'),
        };
    }
}