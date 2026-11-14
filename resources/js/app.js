/**
 * Tutorial guiado (<x-tour>): oscurece la pantalla, resalta una parte y
 * explica qué hace. Cada paso apunta a un elemento con [data-tour="nombre"];
 * los pasos cuyo elemento no está a la vista —una tarjeta que no existe, un
 * chip que en el celular se oculta— se saltan solos, así que el mismo guion
 * sirve para todas las variantes de la pantalla.
 */
document.addEventListener('alpine:init', () => {
    // Cifra que sube desde cero hasta su valor (tablero de inicio). El número
    // final ya viene pintado desde el servidor; esto solo lo anima.
    window.Alpine.data('countUp', (to, decimals = 0) => ({
        shown: '',

        init() {
            const format = (value) => value.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.shown = format(to);

                return;
            }

            const duration = 1100;
            let start = null;
            this.shown = format(0);

            const tick = (now) => {
                start ??= now;
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                this.shown = format(to * eased);

                if (progress < 1) {
                    requestAnimationFrame(tick);
                }
            };

            requestAnimationFrame(tick);
        },
    }));

    window.Alpine.data('tour', (name, steps, autostart) => ({
        name,
        steps,
        visible: [],
        index: 0,
        open: false,
        mobile: false,
        spotlight: null,
        card: { top: 0, left: 0, width: 360 },

        init() {
            this.reposition = this.reposition.bind(this);

            if (autostart) {
                // Que la pantalla termine de pintarse antes de medirla.
                setTimeout(() => this.start(), 500);
            }
        },

        get step() {
            return this.visible[this.index] ?? null;
        },

        get isFirst() {
            return this.index === 0;
        },

        get isLast() {
            return this.index === this.visible.length - 1;
        },

        start() {
            this.visible = this.steps.filter((step) => ! step.target || this.find(step.target));

            if (this.visible.length === 0) {
                return;
            }

            this.index = 0;
            this.open = true;

            window.addEventListener('resize', this.reposition, { passive: true });
            window.addEventListener('scroll', this.reposition, { passive: true, capture: true });

            this.show();
        },

        next() {
            if (this.isLast) {
                this.finish();

                return;
            }

            this.index++;
            this.show();
        },

        prev() {
            if (this.isFirst) {
                return;
            }

            this.index--;
            this.show();
        },

        /**
         * Terminar y saltar son lo mismo para el sistema: la persona ya sabe
         * que existe y dónde volver a verlo, así que no se le abre otra vez.
         */
        finish() {
            this.open = false;
            this.spotlight = null;

            window.removeEventListener('resize', this.reposition);
            window.removeEventListener('scroll', this.reposition, { capture: true });

            this.$root.dispatchEvent(new CustomEvent('tour-finished'));
        },

        find(target) {
            const element = document.querySelector(`[data-tour="${target}"]`);

            if (! element) {
                return null;
            }

            const box = element.getBoundingClientRect();

            return box.width > 0 && box.height > 0 ? element : null;
        },

        show() {
            const element = this.step?.target ? this.find(this.step.target) : null;

            element?.scrollIntoView({ block: 'center', behavior: 'smooth' });

            this.$nextTick(() => {
                this.reposition();
                document.getElementById(`tour-${this.name}-next`)?.focus({ preventScroll: true });
            });

            // El desplazamiento suave tarda: se vuelve a medir cuando termina.
            setTimeout(() => this.reposition(), 400);
        },

        reposition() {
            if (! this.open) {
                return;
            }

            this.mobile = window.innerWidth < 640;

            const element = this.step?.target ? this.find(this.step.target) : null;
            const width = Math.min(380, window.innerWidth - 32);
            const height = document.getElementById(`tour-${this.name}-card`)?.offsetHeight ?? 220;

            if (! element) {
                this.spotlight = null;
                this.card = {
                    top: Math.max(16, (window.innerHeight - height) / 2),
                    left: (window.innerWidth - width) / 2,
                    width,
                };

                return;
            }

            const pad = 6;
            const gap = 14;
            const box = element.getBoundingClientRect();

            this.spotlight = {
                top: box.top - pad,
                left: box.left - pad,
                width: box.width + pad * 2,
                height: box.height + pad * 2,
            };

            // Debajo de lo resaltado; si no cabe, encima; si tampoco, al pie.
            let top = this.spotlight.top + this.spotlight.height + gap;

            if (top + height > window.innerHeight - 16) {
                top = this.spotlight.top - height - gap;
            }

            if (top < 16) {
                top = window.innerHeight - height - 16;
            }

            this.card = {
                top,
                left: Math.min(Math.max(16, this.spotlight.left), window.innerWidth - width - 16),
                width,
            };
        },

        spotlightStyle() {
            if (! this.spotlight) {
                return 'top: 50%; left: 50%; width: 0; height: 0;';
            }

            const { top, left, width, height } = this.spotlight;

            return `top: ${top}px; left: ${left}px; width: ${width}px; height: ${height}px;`;
        },

        cardStyle() {
            // En el celular la tarjeta va siempre al pie: es donde llega el pulgar.
            if (this.mobile) {
                return 'left: 16px; right: 16px; bottom: 16px;';
            }

            return `top: ${this.card.top}px; left: ${this.card.left}px; width: ${this.card.width}px;`;
        },
    }));
});
