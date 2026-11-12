<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar local: {{ $location->name }}</h2>
            <a href="{{ route('locations.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Voltar para a lista</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('locations.update', $location) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('locations._form')

                    <div class="flex justify-end">
                        <x-primary-button>Salvar alterações</x-primary-button>
                    </div>
                </form>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Situação</h3>
                @if ($location->is_active)
                    <p class="mt-2 text-sm text-gray-600">
                        Desative quando a obra for encerrada ou o local deixar de existir.
                        O histórico de equipamentos que passaram por aqui é mantido.
                    </p>
                    <form method="POST" action="{{ route('locations.toggle-active', $location) }}" class="mt-4"
                          onsubmit="return confirm('Confirma a desativação deste local?')">
                        @csrf
                        @method('PATCH')
                        <x-danger-button>Desativar local</x-danger-button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-600">Este local está desativado e não aparece nas novas movimentações.</p>
                    <form method="POST" action="{{ route('locations.toggle-active', $location) }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <x-primary-button>Reativar local</x-primary-button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>