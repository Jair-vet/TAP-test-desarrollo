import { DestroyRef, Directive, ElementRef, NgZone, effect, inject, input } from '@angular/core';
import { prefersReducedMotion } from './motion';

/**
 * Cuenta animada de 0 al valor (ease-out), p. ej. en los contadores del inicio.
 *   <span [appCountUp]="37"></span>
 */
@Directive({ selector: '[appCountUp]' })
export class CountUpDirective {
  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;
  private readonly zone = inject(NgZone);

  readonly appCountUp = input.required<number>();
  readonly countDuration = input(1100);

  constructor() {
    let frame = 0;
    inject(DestroyRef).onDestroy(() => cancelAnimationFrame(frame));

    effect(() => {
      const target = this.appCountUp();
      const duration = this.countDuration();
      cancelAnimationFrame(frame);

      if (prefersReducedMotion()) {
        this.el.textContent = target.toLocaleString('es-MX');
        return;
      }

      const start = performance.now();
      this.zone.runOutsideAngular(() => {
        const step = (now: number) => {
          const t = Math.min(1, (now - start) / duration);
          const eased = 1 - Math.pow(1 - t, 4);
          this.el.textContent = Math.round(target * eased).toLocaleString('es-MX');
          if (t < 1) frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
      });
    });
  }
}
