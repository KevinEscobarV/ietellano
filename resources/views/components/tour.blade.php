@props(['name', 'steps', 'autostart' => false])

{{-- Tutorial guiado. Cada paso es ['target' => 'data-tour del elemento' | null,
     'title' => ..., 'body' => ...]; un paso sin target sale centrado. Se abre
     solo la primera vez (autostart) y con el evento `tour-start` desde el
     botón "¿Cómo funciona?". Terminarlo o saltarlo lo marca como visto en la
     cuenta (ShowsTutorials::finishTutorial). La lógica está en resources/js/app.js. --}}

<div
    x-data="tour(@js($name), @js($steps), @js((bool) $autostart))"
    x-on:tour-start.window="if ($event.detail?.name === name) start()"
    x-on:tour-finished="$wire.finishTutorial(name)"
    x-on:keydown.escape.window="if (open) finish()"
    x-on:keydown.arrow-right.window="if (open) next()"
    x-on:keydown.arrow-left.window="if (open) prev()"
    class="contents"
>
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition.opacity.duration.200ms
            class="fixed inset-0 z-[100]"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="`tour-${name}-title`"
            :aria-describedby="`tour-${name}-body`"
        >
            {{-- Recibe los clics: mientras dura la guía, la pantalla de fondo no se toca. --}}
            <div class="absolute inset-0"></div>

            {{-- El hueco iluminado. La sombra enorme es la que oscurece todo lo demás. --}}
            <div
                class="pointer-events-none fixed rounded-xl shadow-[0_0_0_9999px_rgb(6_16_12/0.62)] ring-2 ring-accent/80 transition-all duration-300 ease-out motion-reduce:transition-none"
                :style="spotlightStyle()"
            ></div>

            <div
                :id="`tour-${name}-card`"
                class="fixed overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 transition-[top,left] duration-300 ease-out motion-reduce:transition-none dark:bg-zinc-900 dark:ring-white/10"
                :style="cardStyle()"
            >
                <div class="h-1 bg-accent"></div>

                <div class="p-5">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span
                            class="text-xs font-semibold uppercase tracking-wide text-accent-content"
                            x-text="@js(__('Paso :current de :total')).replace(':current', index + 1).replace(':total', visible.length)"
                        ></span>

                        <button
                            type="button"
                            x-on:click="finish()"
                            class="rounded-md px-1.5 py-0.5 text-xs font-medium text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        >{{ __('Saltar tutorial') }}</button>
                    </div>

                    <h2 :id="`tour-${name}-title`" class="text-base font-semibold text-zinc-900 dark:text-zinc-100" x-text="step?.title"></h2>
                    <p :id="`tour-${name}-body`" class="mt-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300" x-text="step?.body"></p>

                    <div class="mt-5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-1.5" aria-hidden="true">
                            <template x-for="(_, dot) in visible" :key="dot">
                                <span
                                    class="h-1.5 rounded-full transition-all duration-300"
                                    :class="dot === index ? 'w-5 bg-accent' : 'w-1.5 bg-zinc-200 dark:bg-zinc-700'"
                                ></span>
                            </template>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                x-show="! isFirst"
                                x-on:click="prev()"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >{{ __('Anterior') }}</button>

                            <button
                                type="button"
                                :id="`tour-${name}-next`"
                                x-on:click="next()"
                                class="rounded-lg bg-accent px-3.5 py-1.5 text-sm font-medium text-accent-foreground transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900"
                                x-text="isLast ? @js(__('¡Entendido!')) : @js(__('Siguiente'))"
                            ></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
