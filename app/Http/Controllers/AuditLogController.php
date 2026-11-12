<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('view-audit');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'tipo' => ['nullable', Rule::in(AuditLog::AUDITABLE_TYPES)],
            'item' => ['nullable', 'integer'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where('description', 'like', "%{$search}%"))
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['de'] ?? null, fn ($query, $date) => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['ate'] ?? null, fn ($query, $date) => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->when(
                ($filters['tipo'] ?? null) && ($filters['item'] ?? null),
                fn ($query) => $query->where('subject_type', $filters['tipo'])->where('subject_id', $filters['item'])
            )
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => AuditAction::cases(),
        ]);
    }
}