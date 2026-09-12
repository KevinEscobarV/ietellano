<section class="w-full">
    {{-- Page Header --}}
    <div class="mb-8 flex items-start justify-between print:hidden">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <flux:icon name="document-text" class="size-6 text-zinc-500 dark:text-zinc-400" />
                <flux:heading size="xl" level="1">{{ __('Boletines') }}</flux:heading>
            </div>
            <flux:subheading>{{ __('Generar boletín de notas por estudiante') }}</flux:subheading>
        </div>
        @php
            $downloadParams = $bothSemesters ? ['both' => 1] : [];
        @endphp
        <div class="flex items-center gap-3">
            @if ($cycleId)
                <flux:button variant="filled" icon="arrow-down-tray" :href="route('admin.boletines.download', ['cycle' => $cycleId] + $downloadParams)" target="_blank">
                    {{ __('Descargar ciclo (ZIP)') }}
                </flux:button>
            @endif
            @if ($boletin)
                <flux:button variant="filled" icon="document-arrow-down" :href="route('admin.boletines.download-student', ['cycle' => $cycleId, 'student' => $studentId] + $downloadParams)" target="_blank">
                    {{ __('Descargar PDF') }}
                </flux:button>
                <flux:button variant="primary" icon="printer" x-on:click="window.print()">
                    {{ __('Imprimir / PDF') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Selectors --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 print:hidden">
        <flux:select wire:model.live="cycleId" :label="__('Ciclo')" :placeholder="__('Selecciona un ciclo')">
            @foreach ($cycles as $cycle)
                <flux:select.option :value="$cycle->id">{{ $cycle->label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="studentId" :label="__('Estudiante')" :placeholder="__('Selecciona un estudiante')" :disabled="! $cycleId">
            @foreach ($students as $student)
                <flux:select.option :value="$student->id">{{ $student->last_name }} {{ $student->first_name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($hasPrevious)
        <div class="mb-6 print:hidden">
            <flux:checkbox wire:model.live="bothSemesters" :label="__('Incluir semestre anterior (dos semestres)')" />
        </div>
    @endif

    @if ($boletin)
        <x-boletin-card :boletin="$boletin" :passing-score="$passingScore" />
    @elseif ($cycleId)
        <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700 print:hidden">
            <flux:icon name="user" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="text-zinc-400">{{ __('Selecciona un estudiante para ver su boletín.') }}</flux:text>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700 print:hidden">
            <flux:icon name="document-text" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="text-zinc-400">{{ __('Selecciona un ciclo y un estudiante para generar el boletín.') }}</flux:text>
        </div>
    @endif
</section>
