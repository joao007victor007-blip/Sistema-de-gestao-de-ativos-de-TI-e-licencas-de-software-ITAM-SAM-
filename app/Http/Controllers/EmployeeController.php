<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Services\LicenseAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Enums\AuditAction;
use App\Support\Audit;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['ativos', 'desligados', 'pendencias', 'todos'])],
        ]);

        $status = $filters['status'] ?? 'ativos';

        $employees = Employee::query()
            ->with(['department', 'location'])
            ->withCount(['openAssetAssignments', 'activeLicenseAssignments'])
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('registration', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when($filters['location_id'] ?? null, fn ($query, $id) => $query->where('location_id', $id))
            ->when($status === 'ativos', fn ($query) => $query->where('is_active', true))
            ->when($status === 'desligados', fn ($query) => $query->where('is_active', false))
            ->when($status === 'pendencias', fn ($query) => $query
                ->where('is_active', false)
                ->whereHas('openAssetAssignments'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'filters' => [...$filters, 'status' => $status],
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Employee $employee): View
    {
        $employee->load([
            'department',
            'location',
            'assetAssignments' => fn ($query) => $query
                ->with(['asset.category', 'asset.manufacturer'])
                ->orderByDesc('checked_out_at')
                ->orderByDesc('id'),
            'licenseAssignments' => fn ($query) => $query
                ->with(['license.software', 'asset'])
                ->orderByDesc('assigned_at')
                ->orderByDesc('id'),
        ]);

        return view('employees.show', compact('employee'));
    }

    public function create(): View
    {
        return view('employees.create', ['employee' => new Employee(), ...$this->formOptions()]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create($request->validated());

        return redirect()->route('employees.index')
            ->with('status', "Colaborador {$employee->name} cadastrado.");
    }

    public function edit(Employee $employee): View
    {
        $employee->load([
            'openAssetAssignments.asset.category',
            'openAssetAssignments.asset.manufacturer',
            'activeLicenseAssignments.license',
        ]);

        return view('employees.edit', ['employee' => $employee, ...$this->formOptions($employee)]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()->route('employees.index')
            ->with('status', "Colaborador {$employee->name} atualizado.");
    }

        public function toggleActive(Request $request, Employee $employee, LicenseAssignmentService $licenses): RedirectResponse
    {
        // Reativação
        if (! $employee->is_active) {
            $employee->is_active = true;
            $employee->terminated_at = null;
            $employee->saveQuietly();

            Audit::record(AuditAction::Reactivated, $employee, "Reativou o colaborador {$employee->name}");

            return redirect()->route('employees.show', $employee)
                ->with('status', "Colaborador {$employee->name} reativado.");
        }

        // Desligamento: com equipamentos pendentes, exige confirmação explícita
        $pendingAssets = $employee->openAssetAssignments()->count();

        $request->validate(
            ['acknowledge_pending' => $pendingAssets > 0 ? ['accepted'] : ['nullable']],
            ['acknowledge_pending.accepted' => 'Confirme que está ciente dos equipamentos que ainda estão com o colaborador.'],
        );

        $released = 0;

        DB::transaction(function () use ($request, $employee, $licenses, $pendingAssets, &$released) {
            $employee->is_active = false;
            $employee->terminated_at = now()->toDateString();
            $employee->saveQuietly();

            // Licenças sempre são liberadas no desligamento: não faz sentido mantê-las ocupando vagas
            foreach ($employee->activeLicenseAssignments()->get() as $assignment) {
                $licenses->revoke($assignment, $request->user(), now()->toDateString());
                $released++;
            }

            Audit::record(AuditAction::Terminated, $employee, "Registrou o desligamento de {$employee->name}", [
                'licencas_liberadas' => ['para' => $released],
                'equipamentos_pendentes' => ['para' => $pendingAssets],
            ]);
        });

        $message = "Desligamento de {$employee->name} registrado.";

        if ($released > 0) {
            $message .= " Licenças liberadas: {$released}.";
        }

        if ($pendingAssets > 0) {
            $message .= " Equipamentos aguardando devolução: {$pendingAssets}.";
        }

        return redirect()->route('employees.show', $employee)->with('status', $message);
    }

    /**
     * Opções dos campos de seleção: só setores e locais ativos,
     * mais o valor atual do colaborador (mesmo que tenha sido desativado depois).
     */
    private function formOptions(?Employee $employee = null): array
    {
        return [
            'departments' => Department::where('is_active', true)
                ->orWhere('id', $employee?->department_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'locations' => Location::where('is_active', true)
                ->orWhere('id', $employee?->location_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}