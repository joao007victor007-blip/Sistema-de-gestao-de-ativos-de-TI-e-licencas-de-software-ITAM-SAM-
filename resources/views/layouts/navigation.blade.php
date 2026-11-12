@php
    $cadastrosAtivo = request()->routeIs('locations.*', 'departments.*', 'categories.*', 'manufacturers.*', 'software.*');
    $adminAtivo = request()->routeIs('users.*', 'audit.*', 'backups.*', 'import.*');
    $isAdmin = auth()->user()->can('manage-users');
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <x-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*')">
                        Ativos
                    </x-nav-link>

                    <x-nav-link :href="route('licenses.index')" :active="request()->routeIs('licenses.*')">
                        Licenças
                    </x-nav-link>

                    <x-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                        Colaboradores
                    </x-nav-link>

                    <!-- Menu suspenso: Cadastros -->
                    <div class="flex items-center">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center whitespace-nowrap text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none {{ $cadastrosAtivo ? 'text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
                                    Cadastros
                                    <svg class="ms-1 fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('locations.index')">Locais e obras</x-dropdown-link>
                                <x-dropdown-link :href="route('departments.index')">Setores</x-dropdown-link>
                                <x-dropdown-link :href="route('categories.index')">Categorias</x-dropdown-link>
                                <x-dropdown-link :href="route('manufacturers.index')">Fabricantes</x-dropdown-link>
                                <x-dropdown-link :href="route('software.index')">Softwares</x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>

                    <!-- Menu suspenso: Administração (somente administradores) -->
                    @if ($isAdmin)
                        <div class="flex items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center whitespace-nowrap text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none {{ $adminAtivo ? 'text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
                                        Administração
                                        <svg class="ms-1 fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link :href="route('users.index')">Usuários</x-dropdown-link>
                                    <x-dropdown-link :href="route('audit.index')">Auditoria</x-dropdown-link>
                                    <x-dropdown-link :href="route('backups.index')">Backups</x-dropdown-link>
                                    <x-dropdown-link :href="route('import.index')">Importar planilha</x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div class="whitespace-nowrap">{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*')">
                Ativos
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('licenses.index')" :active="request()->routeIs('licenses.*')">
                Licenças
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                Colaboradores
            </x-responsive-nav-link>

            <div class="px-4 pt-3 text-xs font-medium text-gray-400">Cadastros</div>
            <x-responsive-nav-link :href="route('locations.index')" :active="request()->routeIs('locations.*')">
                Locais e obras
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('departments.index')" :active="request()->routeIs('departments.*')">
                Setores
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                Categorias
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('manufacturers.index')" :active="request()->routeIs('manufacturers.*')">
                Fabricantes
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('software.index')" :active="request()->routeIs('software.*')">
                Softwares
            </x-responsive-nav-link>

            @if ($isAdmin)
                <div class="px-4 pt-3 text-xs font-medium text-gray-400">Administração</div>
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    Usuários
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('audit.index')" :active="request()->routeIs('audit.*')">
                    Auditoria
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('backups.index')" :active="request()->routeIs('backups.*')">
                    Backups
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('import.index')" :active="request()->routeIs('import.*')">
                    Importar planilha
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>