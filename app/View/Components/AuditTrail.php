<?php

namespace App\View\Components;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Últimas alterações de um item, vindas da auditoria.
 * Só aparece para quem tem a permissão view-audit (administradores).
 */
class AuditTrail extends Component
{
    public function __construct(public Model $subject, public int $limit = 15)
    {
    }

    public function shouldRender(): bool
    {
        return Gate::allows('view-audit');
    }

    public function render(): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->where('subject_type', $this->subject::class)
            ->where('subject_id', $this->subject->getKey())
            ->orderByDesc('id')
            ->limit($this->limit)
            ->get();

        return view('components.audit-trail', [
            'logs' => $logs,
            'fullUrl' => route('audit.index', ['tipo' => $this->subject::class, 'item' => $this->subject->getKey()]),
        ]);
    }
}