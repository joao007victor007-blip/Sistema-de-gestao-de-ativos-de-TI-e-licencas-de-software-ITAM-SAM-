<?php

namespace App\Http\Controllers;

use App\Services\Import\InventoryImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    private const SESSION_KEY = 'import_file';

    public function index(): View
    {
        return view('import.index');
    }

    public function template(): BinaryFileResponse
    {
        return response()->download(resource_path('templates/modelo-importacao.xlsx'));
    }

    public function preview(Request $request, InventoryImporter $importer): View
    {
        $request->validate(
            ['planilha' => ['required', 'file', 'extensions:xlsx', 'max:5120']],
            [],
            ['planilha' => 'planilha'],
        );

        $this->discardPending($request);

        // O arquivo fica em storage/app/private, fora do alcance da web
        $path = $request->file('planilha')->storeAs('imports', Str::uuid() . '.xlsx', 'local');
        $request->session()->put(self::SESSION_KEY, $path);

        $result = $importer->run(Storage::disk('local')->path($path), $request->user(), commit: false);

        // Com erros, a planilha não poderá ser confirmada: descarta o arquivo
        if ($result->hasErrors()) {
            $this->discardPending($request);
        }

        return view('import.preview', ['result' => $result]);
    }

    public function store(Request $request, InventoryImporter $importer): RedirectResponse|View
    {
        $path = $request->session()->get(self::SESSION_KEY);

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return redirect()->route('import.index')
                ->with('error', 'A prévia expirou ou tinha erros. Envie a planilha novamente.');
        }

        $result = $importer->run(Storage::disk('local')->path($path), $request->user(), commit: true);
        $this->discardPending($request);

        if ($result->hasErrors()) {
            return view('import.preview', ['result' => $result]);
        }

        return redirect()->route('import.index')
            ->with('status', 'Importação concluída.')
            ->with('summary', $result->summary());
    }

    private function discardPending(Request $request): void
    {
        if ($path = $request->session()->pull(self::SESSION_KEY)) {
            Storage::disk('local')->delete($path);
        }
    }
}