<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Exceptions\MovementException;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\License;
use App\Models\LicenseAssignment;
use App\Services\LicenseAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LicenseAssignmentController extends Controller
{
    public function __construct(private LicenseAssignmentService $service)
    {
    }

    public function create(License $license): View|RedirectResponse
    {
        if ($reason = $license->assignmentBlockReason()) {
            return redirect()->route('licenses.show', $license)->with('error', $reason);
        }

        $license->load('software')->loadCount('activeAssignments');

        return view('licenses.assign', [
            'license' => $license,
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'registration']),
            'assets' => Asset::where('status', '!=', AssetStatus::Descartado->value)
                ->with(['category', 'manufacturer'])
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request, License $license): RedirectResponse
    {
        $data = $request->validate(
            [
                'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
                'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
                'assigned_at' => ['required', 'date', 'before_or_equal:today'],
                'notes' => ['nullable', 'string', 'max:500'],
            ],
            [],
            [
                'employee_id' => 'colaborador',
                'asset_id' => 'equipamento',
                'assigned_at' => 'data da atribuição',
                'notes' => 'observações',
            ],
        );

        try {
            $this->service->assign($license, $request->user(), $data);
        } catch (MovementException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('licenses.show', $license)->with('status', 'Licença atribuída.');
    }

    public function revoke(Request $request, LicenseAssignment $assignment): RedirectResponse
    {
        $data = $request->validate(
            ['revoked_at' => ['required', 'date', 'before_or_equal:today']],
            [],
            ['revoked_at' => 'data da liberação'],
        );

        try {
            $this->service->revoke($assignment, $request->user(), $data['revoked_at']);
        } catch (MovementException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('licenses.show', $assignment->license_id)->with('status', 'Licença liberada.');
    }
}