<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar: {{ $item->name }}</h2>
            <a href="{{ route($labels['route'] . '.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Voltar para a lista</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route($labels['route'] . '.update', $item) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('catalog._form')

                    <div class="flex justify-end">
                        <x-primary-button>Salvar alterações</x-primary-button>
                    </div>
                </form>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Situação</h3>
                @if ($item->is_active)
                    <p class="mt-2 text-sm text-gray-600">
                        Ao desativar, este item deixa de aparecer nos novos cadastros. Os registros antigos que o usam são mantidos.
                    </p>
                    <form method="POST" action="{{ route($labels['route'] . '.toggle-active', $item) }}" class="mt-4"
                          onsubmit="return confirm('Confirma a desativação?')">
                        @csrf
                        @method('PATCH')
                        <x-danger-button>Desativar</x-danger-button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-600">Este item está desativado.</p>
                    <form method="POST" action="{{ route($labels['route'] . '.toggle-active', $item) }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <x-primary-button>Reativar</x-primary-button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>