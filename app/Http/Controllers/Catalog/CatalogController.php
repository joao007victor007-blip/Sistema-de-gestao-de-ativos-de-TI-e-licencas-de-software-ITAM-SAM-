<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\CatalogModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lógica comum dos cadastros simples. Cada cadastro concreto
 * informa apenas qual modelo usa e quais textos exibe.
 */
abstract class CatalogController extends Controller
{
    /** Classe do modelo, por exemplo Department::class */
    abstract protected function modelClass(): string;

    /** Textos e nome de rota usados nas telas */
    abstract protected function labels(): array;

    public function index(): View
    {
        $items = $this->modelClass()::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25);

        return view('catalog.index', ['items' => $items, 'labels' => $this->labels()]);
    }

    public function create(): View
    {
        return view('catalog.create', [
            'item' => app($this->modelClass()),
            'labels' => $this->labels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $item = $this->modelClass()::create($this->validated($request));

        return $this->backToIndex($item, 'cadastrad');
    }

    public function edit(int $id): View
    {
        return view('catalog.edit', ['item' => $this->find($id), 'labels' => $this->labels()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $item = $this->find($id);
        $item->update($this->validated($request, $item));

        return $this->backToIndex($item, 'atualizad');
    }

    public function toggleActive(int $id): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $item = $this->find($id);
        $item->is_active = ! $item->is_active;
        $item->save();

        return $this->backToIndex($item, $item->is_active ? 'reativad' : 'desativad');
    }

    private function find(int $id): CatalogModel
    {
        return $this->modelClass()::findOrFail($id);
    }

    private function validated(Request $request, ?CatalogModel $item = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $table = app($this->modelClass())->getTable();

        return $request->validate(
            [
                'name' => ['required', 'string', 'max:120', Rule::unique($table, 'name')->ignore($item?->id)],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            [],
            ['name' => 'nome', 'notes' => 'observações'],
        );
    }

    private function backToIndex(CatalogModel $item, string $verbStem): RedirectResponse
    {
        $labels = $this->labels();
        $verb = $verbStem . ($labels['feminine'] ? 'a' : 'o');

        return redirect()->route($labels['route'] . '.index')
            ->with('status', "{$labels['singular']} {$item->name} {$verb}.");
    }
}