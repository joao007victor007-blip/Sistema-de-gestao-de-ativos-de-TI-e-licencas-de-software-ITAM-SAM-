@php
    $fieldClasses = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
    $labelClasses = 'block font-medium text-sm text-gray-700';
@endphp

{{-- Identificação --}}
<div class="space-y-6"
     x-data="{ assignment: @js(old('assignment_type', $license->assignment_type?->value)) }">
    <h3 class="text-base font-medium text-gray-900">Identificação</h3>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="software_id" value="Software" />
            <select id="software_id" name="software_id" required class="{{ $fieldClasses }}">
                <option value="">Selecione</option>
                @foreach ($softwareList as $software)
                    <option value="{{ $software->id }}" @selected((string) old('software_id', $license->software_id) === (string) $software->id)>
                        {{ $software->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Não encontrou? Cadastre em Cadastros, Softwares.</p>
            <x-input-error :messages="$errors->get('software_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="name" value="Nome da licença" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name', $license->name)" required />
            <p class="mt-1 text-xs text-gray-500">Exemplos: "Microsoft 365 Business Standard 2026", "AutoCAD LT, contrato 4821".</p>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div>
            <x-input-label for="license_type" value="Tipo de licença" />
            <select id="license_type" name="license_type" required class="{{ $fieldClasses }}">
                @foreach ($licenseTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('license_type', $license->license_type?->value) === $type->value)>
                        {{ $type->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('license_type')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="assignment_type" value="Forma de atribuição" />
            <select id="assignment_type" name="assignment_type" required x-model="assignment" class="{{ $fieldClasses }}">
                @foreach ($assignmentTypes as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('assignment_type')" class="mt-2" />
        </div>
        <div x-show="assignment !== 'corporate'">
            <x-input-label for="seats" value="Licenças contratadas" />
            <x-text-input id="seats" name="seats" type="number" min="1" class="mt-1 block w-full"
                          :value="old('seats', $license->seats ?? 1)" />
            <x-input-error :messages="$errors->get('seats')" class="mt-2" />
        </div>
    </div>

    <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-600">
        <p x-show="assignment === 'per_user'">
            <strong>Por usuário:</strong> cada colaborador ocupa uma licença.
            Exemplo: Microsoft 365 contratado para 10 pessoas = 10 licenças.
        </p>
        <p x-show="assignment === 'per_device'">
            <strong>Por dispositivo:</strong> cada equipamento ocupa uma licença, independentemente de quem o usa.
            Exemplo: Windows instalado em 5 notebooks = 5 licenças.
        </p>
        <p x-show="assignment === 'corporate'">
            <strong>Corporativa:</strong> uso liberado para toda a empresa, sem atribuição a pessoas ou equipamentos.
            Exemplos: Nextcloud, antivírus corporativo, ERP. O sistema acompanha datas, custo e acesso administrativo.
        </p>
    </div>
</div>

{{-- Vigência e custos --}}
<div class="space-y-6 border-t border-gray-200 pt-6"
     x-data="{ autoRenew: @js((bool) old('auto_renew', $license->auto_renew)) }">
    <h3 class="text-base font-medium text-gray-900">Vigência e custos</h3>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div>
            <label for="billing_cycle" class="{{ $labelClasses }}">Periodicidade</label>
            <select id="billing_cycle" name="billing_cycle" class="{{ $fieldClasses }}">
                <option value="">Não informada</option>
                @foreach ($billingCycles as $cycle)
                    <option value="{{ $cycle->value }}" @selected(old('billing_cycle', $license->billing_cycle?->value) === $cycle->value)>
                        {{ $cycle->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('billing_cycle')" class="mt-2" />
        </div>
        <div class="sm:col-span-2 flex items-end pb-2">
            <label for="auto_renew" class="inline-flex items-start gap-2">
                <input id="auto_renew" type="checkbox" name="auto_renew" value="1" x-model="autoRenew"
                       class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="text-sm text-gray-700">
                    Renovação automática
                    <span class="block text-xs text-gray-500">A assinatura renova sozinha e é cobrada todo mês ou todo ano.</span>
                </span>
            </label>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div>
            <label for="purchase_date" class="{{ $labelClasses }}">Data de aquisição</label>
            <x-text-input id="purchase_date" name="purchase_date" type="date" class="mt-1 block w-full"
                          :value="old('purchase_date', $license->purchase_date?->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('purchase_date')" class="mt-2" />
        </div>
        <div>
            <label for="expires_at" class="{{ $labelClasses }}" x-text="autoRenew ? 'Próxima renovação' : 'Vencimento'">Vencimento</label>
            <x-text-input id="expires_at" name="expires_at" type="date" class="mt-1 block w-full"
                          :value="old('expires_at', $license->expires_at?->format('Y-m-d'))" />
            <p class="mt-1 text-xs text-gray-500" x-show="autoRenew">
                Data da próxima cobrança. Depois dela, o sistema avança sozinho para o próximo ciclo.
            </p>
            <p class="mt-1 text-xs text-gray-500" x-show="! autoRenew">
                Obrigatório para assinaturas: a data em que o contrato termina se não for renovado.
            </p>
            <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
        </div>
        <div>
            <label for="alert_days" class="{{ $labelClasses }}">Avisar quantos dias antes</label>
            <x-text-input id="alert_days" name="alert_days" type="number" min="1" max="365" class="mt-1 block w-full"
                          :value="old('alert_days', $license->alert_days)" required />
            <x-input-error :messages="$errors->get('alert_days')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div>
            <label for="cost" class="{{ $labelClasses }}">Valor (R$)</label>
            <x-text-input id="cost" name="cost" type="number" step="0.01" min="0" class="mt-1 block w-full"
                          :value="old('cost', $license->cost)" />
            <x-input-error :messages="$errors->get('cost')" class="mt-2" />
        </div>
        <div>
            <label for="supplier" class="{{ $labelClasses }}">Fornecedor</label>
            <x-text-input id="supplier" name="supplier" type="text" class="mt-1 block w-full"
                          :value="old('supplier', $license->supplier)" />
            <x-input-error :messages="$errors->get('supplier')" class="mt-2" />
        </div>
        <div>
            <label for="invoice_number" class="{{ $labelClasses }}">Nota fiscal ou pedido</label>
            <x-text-input id="invoice_number" name="invoice_number" type="text" class="mt-1 block w-full"
                          :value="old('invoice_number', $license->invoice_number)" />
            <x-input-error :messages="$errors->get('invoice_number')" class="mt-2" />
        </div>
    </div>
</div>

{{-- Acesso --}}
<div class="space-y-6 border-t border-gray-200 pt-6">
    <h3 class="text-base font-medium text-gray-900">Acesso</h3>

    <div>
        <x-input-label for="admin_account_email" value="E-mail da conta administradora" />
        <x-text-input id="admin_account_email" name="admin_account_email" type="email" class="mt-1 block w-full"
                      :value="old('admin_account_email', $license->admin_account_email)" autocomplete="off" />
        <p class="mt-1 text-xs text-gray-500">A conta usada para gerenciar a assinatura no portal do fabricante.</p>
        <x-input-error :messages="$errors->get('admin_account_email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="license_key" value="Chave de ativação" />
        <textarea id="license_key" name="license_key" rows="2" autocomplete="off" spellcheck="false"
                  class="{{ $fieldClasses }} font-mono"
                  placeholder="{{ $license->exists && $license->hasKey() ? 'Chave já cadastrada. Deixe em branco para manter a atual.' : '' }}">{{ old('license_key') }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Gravada de forma criptografada. Depois de salva, só pode ser vista pelo botão "Mostrar chave".</p>
        <x-input-error :messages="$errors->get('license_key')" class="mt-2" />
    </div>
</div>


{{-- Nota fiscal (somente no cadastro; depois, use "Documentos anexados") --}}
@unless ($license->exists)
    <div class="space-y-6 border-t border-gray-200 pt-6">
        <div>
            <x-input-label for="invoice_file" value="Arquivo da nota fiscal (opcional)" />
            <input id="invoice_file" type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png"
                   class="mt-1 block w-full text-sm text-gray-700 file:me-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:text-gray-700 hover:file:bg-gray-200">
            <p class="mt-1 text-xs text-gray-500">
                PDF, JPG ou PNG, até 2 MB. Outros documentos podem ser anexados depois, na página da licença.
                Se o formulário voltar com algum erro, selecione o arquivo de novo.
            </p>
            <x-input-error :messages="$errors->get('invoice_file')" class="mt-2" />
        </div>
    </div>
@endunless

{{-- Observações --}}
<div class="space-y-6 border-t border-gray-200 pt-6">
    <div>
        <x-input-label for="notes" value="Observações" />
        <textarea id="notes" name="notes" rows="3" class="{{ $fieldClasses }}">{{ old('notes', $license->notes) }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>
</div>