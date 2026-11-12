@props(['changes'])

<dl class="mt-2 space-y-1 text-xs text-gray-600">
    @foreach ($changes as $field => $change)
        <div>
            <dt class="inline font-medium text-gray-700">{{ \App\Models\AuditLog::fieldLabel($field) }}:</dt>
            <dd class="inline">
                @if (array_key_exists('de', $change))
                    de <span class="text-gray-900">{{ \App\Models\AuditLog::formatValue($field, $change['de']) }}</span>
                    para <span class="text-gray-900">{{ \App\Models\AuditLog::formatValue($field, $change['para'] ?? null) }}</span>
                @else
                    <span class="text-gray-900">{{ \App\Models\AuditLog::formatValue($field, $change['para'] ?? null) }}</span>
                @endif
            </dd>
        </div>
    @endforeach
</dl>