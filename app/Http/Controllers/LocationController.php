<?php

namespace App\Http\Controllers;

use App\Enums\LocationType;
use App\Http\Requests\LocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        $locations = Location::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25);

        return view('locations.index', compact('locations'));
    }

    public function create(): View
    {
        return view('locations.create', [
            'location' => new Location(),
            'types' => LocationType::cases(),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::create($request->validated());

        return redirect()->route('locations.index')
            ->with('status', "Local {$location->name} cadastrado.");
    }

    public function edit(Location $location): View
    {
        return view('locations.edit', [
            'location' => $location,
            'types' => LocationType::cases(),
        ]);
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()->route('locations.index')
            ->with('status', "Local {$location->name} atualizado.");
    }

    public function toggleActive(Location $location): RedirectResponse
    {
        $location->is_active = ! $location->is_active;
        $location->save();

        $situacao = $location->is_active ? 'reativado' : 'desativado';

        return redirect()->route('locations.index')
            ->with('status', "Local {$location->name} {$situacao}.");
    }
}