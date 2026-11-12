<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Novo local</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('locations.store') }}" class="space-y-6">
                    @csrf
                    @include('locations._form')

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('locations.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Cancelar</a>
                        <x-primary-button>Cadastrar local</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>