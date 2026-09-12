<section class="w-full">
    <div class="mb-6">
        <div class="mb-1 flex items-center gap-2">
            <flux:icon name="squares-2x2" class="size-6 text-zinc-500 dark:text-zinc-400" />
            <flux:heading size="xl" level="1">{{ __('Estructura académica') }}</flux:heading>
        </div>
        <flux:subheading>{{ __('Administrar ciclos, grupos, materias, cursos y áreas') }}</flux:subheading>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 flex flex-wrap gap-1 border-b border-zinc-200 dark:border-zinc-700">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                wire:click="selectTab('{{ $key }}')"
                @class([
                    '-mb-px border-b-2 px-4 py-2 text-sm font-medium transition',
                    'border-zinc-900 text-zinc-900 dark:border-white dark:text-white' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
                ])
            >
                {{ __($label) }}
            </button>
        @endforeach
    </div>

    {{-- Active panel --}}
    <div wire:key="structure-panel-{{ $tab }}">
        @switch($tab)
            @case('groups')
                @livewire('admin.groups')
                @break
            @case('subjects')
                @livewire('admin.subjects')
                @break
            @case('courses')
                @livewire('admin.courses')
                @break
            @case('areas')
                @livewire('admin.areas')
                @break
            @case('calendar')
                @livewire('admin.calendar')
                @break
            @default
                @livewire('admin.cycles')
        @endswitch
    </div>
</section>
