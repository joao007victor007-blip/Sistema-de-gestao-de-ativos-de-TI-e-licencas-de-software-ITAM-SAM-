<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nova licença</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                 <form method="POST" action="{{ route('licenses.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @include('licenses._form')

                    <div class="flex items-center justify-end gap-4 border-t border-gray-200 pt-6">
                        <a href="{{ route('licenses.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Cancelar</a>
                        <x-primary-button>Cadastrar licença</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>