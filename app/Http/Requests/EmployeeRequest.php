<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'registration' => $this->filled('registration') ? trim((string) $this->input('registration')) : null,
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'registration' => [
                'nullable', 'string', 'max:30',
                Rule::unique('employees', 'registration')->ignore($this->route('employee')),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'registration' => 'matrícula',
            'job_title' => 'cargo',
            'department_id' => 'setor',
            'location_id' => 'local',
        ];
    }
}