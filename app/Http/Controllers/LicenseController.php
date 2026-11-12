<?php

namespace App\Http\Controllers;

use App\Enums\BillingCycle;
use App\Enums\LicenseAssignmentType;
use App\Enums\LicenseType;
use App\Http\Requests\LicenseRequest;
use App\Models\License;
use App\Models\Software;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Enums\AuditAction;
use App\Support\Audit;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\DB;

class LicenseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'software_id' => ['nullable', 'integer'],
            'situacao' => ['nullable', Rule::in(['ativas', 'vencendo', 'renovando', 'vencidas', 'baixadas', 'todas'])],
        ]);

        $situacao = $filters['situacao'] ?? 'ativas';
        

        $licenses = License::query()
            ->with('software')->withCount('activeAssignments')
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhereHas('software', fn ($software) => $software->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['software_id'] ?? null, fn ($query, $id) => $query->where('software_id', $id))
            ->when($situacao === 'ativas', fn ($query) => $query->whereNull('retired_at'))
            ->when($situacao === 'baixadas', fn ($query) => $query->whereNotNull('retired_at'))
                        ->when($situacao === 'vencidas', fn ($query) => $query->expired())
            ->when($situacao === 'vencendo', fn ($query) => $query->expiringSoon())->when($situacao === 'renovando', fn ($query) => $query->renewingSoon())
            // Vencimentos mais próximos primeiro; licenças sem vencimento no final
            ->orderByRaw('expires_at IS NULL, expires_at')
            ->paginate(25)
            ->withQueryString();

        return view('licenses.index', [
            'licenses' => $licenses,
            'filters' => [...$filters, 'situacao' => $situacao],
            'softwareList' => Software::orderBy('name')->get(['id', 'name']),
        ]);
    }

        public function show(License $license): View
    {
        return view('licenses.show', ['license' => $this->loadDetails($license), 'revealedKey' => null]);
    }

        public function revealKey(License $license): Response
    {
        Gate::authorize('reveal-license-keys');

        $license = $this->loadDetails($license);

        Audit::record(AuditAction::KeyRevealed, $license, "Visualizou a chave de {$license->name}");

        // A página com a chave visível não pode ficar guardada no cache do navegador
        return response()
            ->view('licenses.show', ['license' => $license, 'revealedKey' => $license->license_key])
            ->header('Cache-Control', 'no-store, private');
    }

    /** Dados exibidos na página de detalhes, inclusive o uso das licenças */
        /** Dados exibidos na página de detalhes, inclusive o uso das licenças e os anexos */
    private function loadDetails(License $license): License
    {
        return $license->load([
            'software',
            'attachments.uploadedBy',
            'assignments' => fn ($query) => $query
                ->with(['employee', 'asset', 'assignedBy', 'revokedBy'])
                ->orderByDesc('assigned_at')
                ->orderByDesc('id'),
        ]);
    }

    public function create(): View
    {
        $license = new License([
            'license_type' => LicenseType::Assinatura,
            'assignment_type' => LicenseAssignmentType::PerUser,
            'seats' => 1,
            'alert_days' => 10,
        ]);

        return view('licenses.create', ['license' => $license, ...$this->formOptions($license)]);
    }

        public function store(LicenseRequest $request, AttachmentService $attachments): RedirectResponse
    {
        $data = $request->validated();

        // Licença e nota fiscal são gravadas juntas: se o anexo falhar, o cadastro também é desfeito
        $license = DB::transaction(function () use ($request, $data, $attachments) {
            $license = new License(Arr::except($data, ['license_key', 'invoice_file']));

            if (filled($data['license_key'] ?? null)) {
                $license->license_key = $data['license_key'];
            }

            $license->save();

            if ($request->hasFile('invoice_file')) {
                $attachments->store($license, $request->file('invoice_file'), 'nota_fiscal', $request->user());
            }

            return $license;
        });

        return redirect()->route('licenses.show', $license)
            ->with('status', "Licença {$license->name} cadastrada.");
    }

    public function edit(License $license): View
    {
        return view('licenses.edit', ['license' => $license, ...$this->formOptions($license)]);
    }

    public function update(LicenseRequest $request, License $license): RedirectResponse
    {
        $data = $request->validated();

        $license->fill(Arr::except($data, ['license_key']));

        // Campo da chave em branco = manter a chave atual
        if (filled($data['license_key'] ?? null)) {
            $license->license_key = $data['license_key'];
        }

        $license->save();

        return redirect()->route('licenses.show', $license)
            ->with('status', "Licença {$license->name} atualizada.");
    }

        public function retire(Request $request, License $license): RedirectResponse
    {
        Gate::authorize('dispose-assets');

        $data = $request->validate(
            ['retirement_reason' => ['required', 'string', 'max:255']],
            [],
            ['retirement_reason' => 'motivo da baixa'],
        );

        if ($license->isRetired()) {
            return back()->with('error', 'Esta licença já foi baixada.');
        }

        $license->retired_at = now()->toDateString();
        $license->retirement_reason = $data['retirement_reason'];
        $license->saveQuietly();

        Audit::record(AuditAction::Retired, $license, "Deu baixa em {$license->name}", [
            'motivo' => ['para' => $data['retirement_reason']],
        ]);

        return redirect()->route('licenses.show', $license)
            ->with('status', "Baixa da licença {$license->name} registrada.");
    }

    private function formOptions(License $license): array
    {
        return [
            'softwareList' => Software::where('is_active', true)
                ->orWhere('id', $license->software_id)->orderBy('name')->get(['id', 'name']),
            'licenseTypes' => LicenseType::cases(),
            'assignmentTypes' => LicenseAssignmentType::cases(),
            'billingCycles' => BillingCycle::cases(),
        ];
    }
}