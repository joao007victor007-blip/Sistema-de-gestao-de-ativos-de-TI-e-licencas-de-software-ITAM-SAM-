<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Exceptions\MovementException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\Location;
use App\Services\AssetMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetMovementController extends Controller
{
    public function __construct(private AssetMovementService $movements)
    {
    }

    public function checkoutForm(Asset $asset): View|RedirectResponse
    {
        if ($asset->status !== AssetStatus::Disponivel) {
            return redirect()->route('assets.show', $asset)
                ->with('error', 'Somente equipamentos disponíveis podem ser entregues.');
        }

        return view('assets.checkout', [
            'asset' => $asset,
            'employees' => Employee::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'registration', 'location_id']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function checkout(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate(
            [
                'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
                'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
                'checked_out_at' => ['required', 'date', 'before_or_equal:today'],
                'checkout_condition' => ['nullable', 'string', 'max:255'],
                'term_signed' => ['boolean'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            [],
            [
                'employee_id' => 'colaborador',
                'location_id' => 'local de uso',
                'checked_out_at' => 'data da entrega',
                'checkout_condition' => 'condição na entrega',
                'notes' => 'observações',
            ],
        );

        $data['term_signed'] = $request->boolean('term_signed');

        try {
            $assignment = $this->movements->checkout(
                $asset,
                Employee::findOrFail($data['employee_id']),
                $request->user(),
                $data,
            );
        } catch (MovementException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('assets.show', $asset)
            ->with('status', "Equipamento entregue a {$assignment->employee->name}.");
    }

    public function checkinForm(Asset $asset): View|RedirectResponse
    {
        $assignment = $asset->currentAssignment()->with('employee')->first();

        if ($assignment === null) {
            return redirect()->route('assets.show', $asset)
                ->with('error', 'Este equipamento não está em uso.');
        }

        return view('assets.checkin', [
            'asset' => $asset,
            'assignment' => $assignment,
            'locations' => Location::where('is_active', true)
                ->orWhere('id', $asset->location_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function checkin(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate(
            [
                'checked_in_at' => ['required', 'date', 'before_or_equal:today'],
                'status' => ['required', Rule::in([AssetStatus::Disponivel->value, AssetStatus::Manutencao->value])],
                'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
                'checkin_condition' => ['nullable', 'string', 'max:255'],
            ],
            [],
            [
                'checked_in_at' => 'data da devolução',
                'status' => 'situação após a devolução',
                'location_id' => 'local de destino',
                'checkin_condition' => 'condição na devolução',
            ],
        );

        try {
            $assignment = $this->movements->checkin($asset, $request->user(), $data);
        } catch (MovementException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('assets.show', $asset)
            ->with('status', "Devolução de {$assignment->employee->name} registrada.");
    }

    public function signTerm(Request $request, AssetAssignment $assignment): RedirectResponse
    {
        $data = $request->validate(
            ['term_signed_at' => ['required', 'date', 'before_or_equal:today']],
            [],
            ['term_signed_at' => 'data da assinatura'],
        );

        try {
            $this->movements->signTerm($assignment, $data['term_signed_at']);
        } catch (MovementException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('assets.show', $assignment->asset_id)
            ->with('status', 'Assinatura do termo registrada.');
    }
}