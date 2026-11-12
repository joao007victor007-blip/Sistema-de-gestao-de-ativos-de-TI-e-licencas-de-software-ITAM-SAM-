<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar usuário: {{ $user->name }}</h2>
            <a href="{{ route('users.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Voltar para a lista</a>
        </div>
    </x-slot>

    @php($isSelf = $user->is(auth()->user()))

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            {{-- Dados do usuário --}}
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Dados do usuário</h3>

                <form method="POST" action="{{ route('users.update', $user) }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Nome completo" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $user->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" value="E-mail" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                      :value="old('email', $user->email)" required autocomplete="off" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role" value="Papel" />
                        @if ($isSelf)
                            <input type="hidden" name="role" value="{{ $user->role->value }}">
                        @endif
                        <select id="role" name="role" @disabled($isSelf)
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-gray-100 disabled:text-gray-500">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>
                                    {{ $role->label() }}
                                </option>
                            @endforeach
                        </select>
                        @if ($isSelf)
                            <p class="mt-1 text-xs text-gray-500">Você não pode alterar o seu próprio papel.</p>
                        @endif
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Salvar alterações</x-primary-button>
                    </div>
                </form>
            </section>

            {{-- Situação da conta --}}
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Situação da conta</h3>

                @if ($isSelf)
                    <p class="mt-2 text-sm text-gray-600">Você não pode desativar a sua própria conta.</p>
                @elseif ($user->is_active)
                    <p class="mt-2 text-sm text-gray-600">
                        Ao desativar, o usuário é desconectado imediatamente e não consegue mais entrar.
                        O histórico dele é mantido.
                    </p>
                    <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="mt-4"
                          onsubmit="return confirm('Confirma a desativação deste usuário?')">
                        @csrf
                        @method('PATCH')
                        <x-danger-button>Desativar usuário</x-danger-button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-600">
                        Esta conta está desativada. Ao reativar, o usuário volta a conseguir entrar com a senha atual.
                    </p>
                    <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="mt-4"
                          onsubmit="return confirm('Confirma a reativação deste usuário?')">
                        @csrf
                        @method('PATCH')
                        <x-primary-button>Reativar usuário</x-primary-button>
                    </form>
                @endif
            </section>

            {{-- Senha --}}
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Redefinir senha</h3>

                @if ($isSelf)
                    <p class="mt-2 text-sm text-gray-600">
                        Para trocar a sua própria senha, use a página
                        <a href="{{ route('profile.edit') }}" class="text-indigo-600 underline hover:text-indigo-900">Perfil</a>,
                        que exige a senha atual.
                    </p>
                @else
                    <p class="mt-2 text-sm text-gray-600">
                        A nova senha encerra todas as sessões abertas deste usuário.
                    </p>

                    <form method="POST" action="{{ route('users.reset-password', $user) }}" class="mt-6 space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="password" value="Nova senha" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                                          required autocomplete="new-password" />
                            <p class="mt-1 text-xs text-gray-500">
                                Mínimo de 12 caracteres, com maiúsculas, minúsculas, números e símbolos.
                            </p>
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="password_confirmation" value="Confirme a nova senha" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                          class="mt-1 block w-full" required autocomplete="new-password" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Redefinir senha</x-primary-button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>