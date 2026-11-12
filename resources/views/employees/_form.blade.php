<div>
    <x-input-label for="name" value="Nome completo" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $employee->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="registration" value="Matrícula" />
        <x-text-input id="registration" name="registration" type="text" class="mt-1 block w-full"
                      :value="old('registration', $employee->registration)" />
        <p class="mt-1 text-xs text-gray-500">Opcional. Deixe em branco para terceirizados sem matrícula.</p>
        <x-input-error :messages="$errors->get('registration')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="job_title" value="Cargo" />
        <x-text-input id="job_title" name="job_title" type="text" class="mt-1 block w-full"
                      :value="old('job_title', $employee->job_title)" />
        <x-input-error :messages="$errors->get('job_title')" class="mt-2" />
    </div>
</div>

<div>
    <x-input-label for="email" value="E-mail" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                  :value="old('email', $employee->email)" autocomplete="off" />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="department_id" value="Setor" />
        <select id="department_id" name="department_id"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">Não informado</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) old('department_id', $employee->department_id) === (string) $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="location_id" value="Local de lotação" />
        <select id="location_id" name="location_id"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">Não informado</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}" @selected((string) old('location_id', $employee->location_id) === (string) $location->id)>
                    {{ $location->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
    </div>
</div>