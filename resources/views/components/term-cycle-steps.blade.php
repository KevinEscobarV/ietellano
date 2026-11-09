@props(['years', 'semesters', 'cycleCards', 'cycleId'])

{{-- Pasos 1 y 2 de Boletines, Certificados y Consolidados: el semestre y el
     ciclo. Las propiedades year, semester y cycleId son del componente de
     Livewire que usa el trait BrowsesCyclesByTerm. --}}

<div class="print:hidden">
    {{-- 1 · Semestre --}}
    <div class="mb-8" data-tour="term">
        <div class="mb-3 flex items-center gap-2">
            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">1</span>
            <flux:heading size="lg">{{ __('Semestre') }}</flux:heading>
        </div>

        <div class="flex flex-wrap gap-3">
            <div class="w-32">
                <flux:select wire:model.live="year" :label="__('Año')">
                    @foreach ($years as $option)
                        <flux:select.option :value="(string) $option">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-40">
                <flux:select wire:model.live="semester" :label="__('Semestre')">
                    @foreach ($semesters as $option)
                        <flux:select.option :value="(string) $option">{{ __('Semestre :n', ['n' => $option]) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    {{-- 2 · Ciclo --}}
    <div class="mb-8">
        <div class="mb-3 flex items-center gap-2">
            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">2</span>
            <flux:heading size="lg">{{ __('Ciclo') }}</flux:heading>
        </div>

        @if ($cycleCards->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-200 py-10 text-center dark:border-zinc-700">
                <flux:text class="text-zinc-400">{{ __('No hay ciclos en este semestre.') }}</flux:text>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-tour="cycles">
                @foreach ($cycleCards as $card)
                    @php $active = (string) $card['id'] === $cycleId; @endphp

                    <button
                        type="button"
                        wire:key="cycle-{{ $card['id'] }}"
                        wire:click="$set('cycleId', '{{ $card['id'] }}')"
                        aria-pressed="{{ $active ? 'true' : 'false' }}"
                        @class([
                            'flex flex-col gap-3 rounded-xl border bg-white p-4 text-left transition dark:bg-zinc-900',
                            'border-llano-jade ring-2 ring-llano-emerald/40 dark:border-llano-emerald' => $active,
                            'border-zinc-200 hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:hover:border-zinc-600' => ! $active,
                        ])
                    >
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $card['name'] }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ trans_choice('{0}Sin estudiantes|{1}:count estudiante|[2,*]:count estudiantes', $card['students'], ['count' => $card['students']]) }}
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($card['modules'] as $n => $module)
                                <x-module-status :module="$n" :state="$module['state']" :at="$module['at']" />
                            @endforeach
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>
