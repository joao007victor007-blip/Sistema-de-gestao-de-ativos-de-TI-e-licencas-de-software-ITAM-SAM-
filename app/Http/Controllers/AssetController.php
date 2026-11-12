<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Enums\AuditAction;
use App\Support\Audit;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $statusValues = array_map(fn (AssetStatus $status) => $status->value, AssetStatus::cases());

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['ativos', 'todos', ...$statusValues])],
            'termo' => ['nullable', Rule::in(['pendente'])],
        ]);

        $status = $filters['status'] ?? 'ativos';

        $assets = Asset::query()
            ->with(['category', 'manufacturer', 'location', 'currentAssignment.employee'])
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('asset_tag', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['location_id'] ?? null, fn ($query, $id) => $query->where('location_id', $id))
                        ->when(($filters['termo'] ?? null) === 'pendente', fn ($query) => $query
                ->whereHas('currentAssignment', fn ($assignment) => $assignment->where('term_signed', false)))
            ->when($status === 'ativos', fn ($query) => $query->where('status', '!=', AssetStatus::Descartado->value))
            ->when(! in_array($status, ['ativos', 'todos'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('assets.index', [
            'assets' => $assets,
            'filters' => [...$filters, 'status' => $status],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('name')->get(['id', 'name']),
            'statuses' => AssetStatus::cases(),
        ]);
    }

                public function show(Asset $asset): View
    {
        $asset->load([
            'category',
            'manufacturer',
            'location',
            'attachments.uploadedBy',
            'assignments' => fn ($query) => $query
                ->with(['employee', 'checkedOutBy', 'checkedInBy'])
                ->orderByDesc('checked_out_at')
                ->orderByDesc('id'),
            'licenseAssignments' => fn ($query) => $query
                ->whereNull('revoked_at')
                ->with(['license.software', 'employee']),
        ]);

        return view('assets.show', compact('asset'));
    }

    public function create(): View
    {
        $asset = new Asset(['status' => AssetStatus::Disponivel]);

        return view('assets.create', ['asset' => $asset, ...$this->formOptions($asset)]);
    }

        public function store(AssetRequest $request, AttachmentService $attachments): RedirectResponse
    {
        // Ativo e nota fiscal são gravados juntos: se o anexo falhar, o cadastro também é desfeito
        $asset = DB::transaction(function () use ($request, $attachments) {
            $asset = Asset::create($request->safe()->except('invoice_file'));

            if ($request->hasFile('invoice_file')) {
                $attachments->store($asset, $request->file('invoice_file'), 'nota_fiscal', $request->user());
            }

            return $asset;
        });

        return redirect()->route('assets.show', $asset)
            ->with('status', "Ativo {$asset->displayIdentifier()} cadastrado.");
    }

    public function edit(Asset $asset): View
    {
        return view('assets.edit', ['asset' => $asset, ...$this->formOptions($asset)]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        return redirect()->route('assets.show', $asset)
            ->with('status', "Ativo {$asset->displayIdentifier()} atualizado.");
    }

        public function dispose(Request $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('dispose-assets');

        $data = $request->validate(
            ['disposal_reason' => ['required', 'string', 'max:255']],
            [],
            ['disposal_reason' => 'motivo do descarte'],
        );

        if ($asset->status === AssetStatus::EmUso) {
            return back()->with('error', 'Este equipamento está em uso. Registre a devolução antes de descartá-lo.');
        }

        if ($asset->status === AssetStatus::Descartado) {
            return back()->with('error', 'Este equipamento já foi descartado.');
        }

        if ($asset->licenseAssignments()->whereNull('revoked_at')->exists()) {
            return back()->with('error', 'Este equipamento tem licenças atribuídas. Libere-as antes de registrar o descarte.');
        }

        $previousStatus = $asset->status->value;

        $asset->status = AssetStatus::Descartado;
        $asset->disposed_at = now()->toDateString();
        $asset->disposal_reason = $data['disposal_reason'];
        $asset->saveQuietly();

        Audit::record(AuditAction::Disposed, $asset, "Descartou {$asset->auditLabel()}", [
            'situacao' => ['de' => $previousStatus, 'para' => AssetStatus::Descartado->value],
            'motivo' => ['para' => $data['disposal_reason']],
        ]);

        return redirect()->route('assets.show', $asset)
            ->with('status', "Descarte do ativo {$asset->displayIdentifier()} registrado.");
    }

    /**
     * Opções dos campos de seleção: itens ativos, mais o valor atual do ativo
     * (mesmo que tenha sido desativado depois).
     */
    private function formOptions(Asset $asset): array
    {
        return [
            'categories' => Category::where('is_active', true)
                ->orWhere('id', $asset->category_id)->orderBy('name')->get(['id', 'name']),
            'manufacturers' => Manufacturer::where('is_active', true)
                ->orWhere('id', $asset->manufacturer_id)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('is_active', true)
                ->orWhere('id', $asset->location_id)->orderBy('name')->get(['id', 'name']),
            'statuses' => AssetStatus::editable(),
        ];
    }
}