<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('status') === 'profile-updated')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">Dados do perfil salvos com sucesso.</div>
            @elseif (session('status') === 'password-updated')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">Senha alterada com sucesso.</div>
            @endif

@if (auth()->user()->must_change_password)
    <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
        Por segurança, defina uma nova senha pessoal para continuar usando a intranet.
        A senha atual é a que você recebeu do administrador.
    </div>
@endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                
            </div>
        </div>
    </div>
</x-app-layout>
