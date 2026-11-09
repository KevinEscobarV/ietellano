@props(['window'])

{{-- Una ventana de edición en la lista de Cierre de módulos: qué abre, de
     qué ciclo, hasta cuándo y quién la autorizó. --}}

@php
    $isActive = $window->isActive();
    $date = $window->closes_at->translatedFormat('j \d\e F, g:i a');
@endphp

<li {{ $attributes->class(['px-5 py-4', 'opacity-60' => ! $isActive]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                @if ($window->course)
                    {{ $window->course->subject?->name ?? $window->course->code }}
                    @if ($window->course->group)
                        <span class="font-normal text-zinc-500">· {{ $window->course->group->name }}</span>
                    @endif
                @elseif ($window->period)
                    {{ __('Todo el Módulo :n', ['n' => $window->period]) }}
                @else
                    {{ __('Los dos módulos') }}
                @endif
            </div>
            <div class="text-xs text-zinc-500">
                @if ($window->period)
                    {{ __('Módulo :n', ['n' => $window->period]) }} ·
                @endif
                {{ $window->cycle?->name }}
            </div>
        </div>

        @if ($isActive)
            <flux:button wire:click="confirmWindow({{ $window->id }})" size="xs" variant="ghost">
                {{ __('Cerrar') }}
            </flux:button>
        @endif
    </div>

    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        <span @class([
            'rounded-full px-2 py-0.5 font-medium',
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $isActive,
            'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $isActive,
        ])>
            {{ $isActive ? __('hasta el :date', ['date' => $date]) : __('venció el :date', ['date' => $date]) }}
        </span>

        @if ($window->openedBy)
            <span class="text-zinc-400">{{ __('la abrió :name', ['name' => $window->openedBy->name]) }}</span>
        @endif
    </div>

    @if ($window->note)
        <p class="mt-1.5 text-xs text-zinc-500">{{ $window->note }}</p>
    @endif
</li>
