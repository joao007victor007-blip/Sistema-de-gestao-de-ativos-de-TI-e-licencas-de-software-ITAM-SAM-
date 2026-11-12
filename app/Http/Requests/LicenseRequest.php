<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\LicenseAssignmentType;
use App\Enums\LicenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Attachment;

class LicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    protected function prepareForValidation(): void
    {
        $isCorporate = $this->input('assignment_type') === LicenseAssignmentType::Corporate->value;
        $isSubscription = $this->input('license_type') === LicenseType::Assinatura->value;

        $this->merge([
            'name' => trim((string) $this->input('name')),
            // Renovação automática só existe para assinaturas
            'auto_renew' => $isSubscription && $this->boolean('auto_renew'),
            // Licença corporativa não tem quantidade, mesmo que algo tenha sido digitado antes da troca
            'seats' => $isCorporate ? null : $this->input('seats'),
            'license_key' => $this->filled('license_key') ? trim((string) $this->input('license_key')) : null,
            'admin_account_email' => $this->filled('admin_account_email')
                ? mb_strtolower(trim((string) $this->input('admin_account_email')))
                : null,
        ]);
    }

    /** Quantas licenças deste contrato estão em uso agora (zero no cadastro) */
    private function seatsInUse(): int
    {
        $license = $this->route('license');

        return $license ? $license->activeAssignments()->count() : 0;
    }

    public function rules(): array
    {
        $inUse = $this->seatsInUse();
        $license = $this->route('license');

        // Com atribuições ativas, a forma de atribuição não pode mudar
        $assignmentTypeRules = ['required', Rule::enum(LicenseAssignmentType::class)];
        if ($inUse > 0) {
            $assignmentTypeRules[] = Rule::in([$license->assignment_type->value]);
        }

        // Renovação automática exige periodicidade mensal ou anual
        $billingCycleRules = ['nullable', Rule::enum(BillingCycle::class)];
        if ($this->boolean('auto_renew')) {
            $billingCycleRules = ['required', Rule::in([BillingCycle::Mensal->value, BillingCycle::Anual->value])];
        }

        return [
            'software_id' => ['required', 'integer', Rule::exists('software', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'license_type' => ['required', Rule::enum(LicenseType::class)],
            'assignment_type' => $assignmentTypeRules,
            'seats' => [
                Rule::requiredIf(fn () => $this->input('assignment_type') !== LicenseAssignmentType::Corporate->value),
                'nullable', 'integer', 'min:' . max(1, $inUse), 'max:100000',
            ],
            'license_key' => ['nullable', 'string', 'max:2000'],
            'admin_account_email' => ['nullable', 'string', 'email', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'expires_at' => array_filter([
                'nullable',
                'date',
                'required_if:license_type,' . LicenseType::Assinatura->value,
                $this->filled('purchase_date') ? 'after_or_equal:purchase_date' : null,
            ]),
            'auto_renew' => ['boolean'],
            'billing_cycle' => $billingCycleRules,
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'invoice_file' => ['nullable', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'max:' . Attachment::MAX_KILOBYTES],
        ];
    }

    public function attributes(): array
    {
        return [
            'software_id' => 'software',
            'name' => 'nome da licença',
            'license_type' => 'tipo de licença',
            'assignment_type' => 'forma de atribuição',
            'seats' => 'licenças contratadas',
            'license_key' => 'chave de ativação',
            'admin_account_email' => 'e-mail da conta administradora',
            'purchase_date' => 'data de aquisição',
            'expires_at' => 'próxima renovação ou vencimento',
            'auto_renew' => 'renovação automática',
            'billing_cycle' => 'periodicidade',
            'cost' => 'valor',
            'supplier' => 'fornecedor',
            'invoice_number' => 'nota fiscal ou pedido',
            'alert_days' => 'dias de antecedência do aviso',
            'notes' => 'observações',
            'invoice_file' => 'arquivo da nota fiscal',
        ];
    }

    public function messages(): array
    {
        $messages = [
            'expires_at.required_if' => 'Assinaturas precisam da data da próxima renovação ou do vencimento.',
            'seats.required' => 'Informe quantas licenças foram contratadas.',
            'assignment_type.in' => 'Esta licença tem atribuições ativas. Libere-as antes de mudar a forma de atribuição.',
            'billing_cycle.required' => 'Renovação automática precisa de periodicidade mensal ou anual.',
            'billing_cycle.in' => 'Renovação automática precisa de periodicidade mensal ou anual.',
        ];

        if ($this->seatsInUse() > 0) {
            $messages['seats.min'] = 'Há :min licenças em uso. Libere atribuições antes de reduzir a quantidade.';
        }

        return $messages;
    }
}