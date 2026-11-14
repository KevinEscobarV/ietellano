<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    @php
        $user = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin']);

        // El menú es un dato y no marcado suelto porque lo leen dos piezas:
        // la barra lateral y la ruta de la barra superior («Sección / Página»).
        // Cada ítem: [icono, ruta, patrones que lo marcan como actual, nombre].
        $menu = array_filter([
            '' => $user->isTeacherOnly() ? [] : [
                ['home', 'dashboard', ['dashboard'], __('Inicio')],
            ],
            __('Docencia') => $user->isTeacher() ? [
                ['book-open', 'teacher.courses', ['teacher.*'], __('Mis materias')],
            ] : [],
            __('Académico') => $isAdmin ? [
                ['academic-cap', 'admin.students', ['admin.students'], __('Estudiantes')],
                ['user-group', 'admin.teachers', ['admin.teachers'], __('Docentes')],
                ['clipboard-document-check', 'admin.attendance', ['admin.attendance'], __('Asistencia')],
                ['squares-2x2', 'admin.structure', ['admin.structure'], __('Estructura académica')],
            ] : [],
            __('Calificaciones') => $isAdmin ? [
                ['arrow-up-tray', 'admin.grades', ['admin.grades'], __('Importar notas')],
                ['pencil-square', 'admin.grade-editor', ['admin.grade-editor', 'admin.grade-editor.*'], __('Editar notas')],
                ['lock-closed', 'admin.closure', ['admin.closure'], __('Cierre de módulos')],
            ] : [],
            __('Documentos') => $isAdmin ? [
                ['document-text', 'admin.boletines', ['admin.boletines'], __('Boletines')],
                ['document-check', 'admin.certificates', ['admin.certificates'], __('Certificados')],
                ['table-cells', 'admin.consolidados', ['admin.consolidados'], __('Consolidados')],
            ] : [],
            __('Sistema') => $isAdmin ? [
                ['users', 'admin.users', ['admin.users'], __('Usuarios')],
                ['shield-check', 'admin.roles', ['admin.roles'], __('Roles')],
            ] : [],
        ]);

        $crumb = request()->routeIs('*.edit') ? [__('Cuenta'), __('Settings')] : ['', ''];

        foreach ($menu as $section => $items) {
            foreach ($items as [, , $patterns, $name]) {
                if (request()->routeIs(...$patterns)) {
                    $crumb = [$section, $name];
                }
            }
        }
    @endphp
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-950">
        <flux:sidebar sticky collapsible="mobile" class="bg-white dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @foreach ($menu as $section => $items)
                    @if ($section === '')
                        @foreach ($items as [$icon, $route, $patterns, $name])
                            <flux:sidebar.item :icon="$icon" :href="route($route)" :current="request()->routeIs(...$patterns)" wire:navigate>
                                {{ $name }}
                            </flux:sidebar.item>
                        @endforeach
                    @else
                        <flux:sidebar.group :heading="$section" class="grid">
                            @foreach ($items as [$icon, $route, $patterns, $name])
                                <flux:sidebar.item :icon="$icon" :href="route($route)" :current="request()->routeIs(...$patterns)" wire:navigate>
                                    {{ $name }}
                                </flux:sidebar.item>
                            @endforeach
                        </flux:sidebar.group>
                    @endif
                @endforeach
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="hidden border-t border-zinc-100 pt-2 lg:block dark:border-zinc-800">
                <x-desktop-user-menu :name="$user->name" />
            </div>
        </flux:sidebar>

        {{-- Barra superior: dónde estoy y, a la derecha, la cuenta. --}}
        <flux:header class="max-lg:border-b max-lg:border-zinc-200 max-lg:bg-white lg:pt-4 max-lg:dark:border-zinc-800 max-lg:dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <nav aria-label="{{ __('Ubicación') }}" class="flex min-w-0 items-center gap-2 text-sm max-lg:ms-2">
                @if ($crumb[0] !== '')
                    <span class="truncate text-zinc-400 max-sm:hidden dark:text-zinc-500">{{ $crumb[0] }}</span>
                    <span class="text-zinc-300 max-sm:hidden dark:text-zinc-600">/</span>
                @endif
                <span class="truncate font-semibold text-zinc-800 dark:text-zinc-100">{{ $crumb[1] }}</span>
            </nav>

            <flux:spacer />

            <flux:button :href="route('profile.edit')" wire:navigate variant="subtle" size="sm" icon="cog-6-tooth" :tooltip="__('Settings')" class="max-lg:hidden" />

            <flux:dropdown position="top" align="end" class="lg:hidden">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
