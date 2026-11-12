<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar ativo {{ $asset->displayIdentifier() }}</h2>
            <a href="{{ route('assets.show', $asset) }}" class="text-sm text-gray-600 underline hover:text-gray-900">Voltar para os detalhes</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('assets.update', $asset) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('assets._form')

                    <div class="flex justify-end border-t border-gray-200 pt-6">
                        <x-primary-button>Salvar alterações</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>