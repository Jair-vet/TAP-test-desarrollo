import { DestroyRef, Directive, ElementRef, NgZone, OnInit, inject, input } from '@angular/core';
import { motionAllowed } from './motion';

/**
 * Inclinación 3D que sigue al cursor + luz (spotlight).
 *   <div class="card tilt spotlight" appTilt [tiltMax]="8">…</div>
 *
 * Rendimiento: escucha el mouse FUERA de la zona de Angular (no dispara
 * detección de cambios) y escribe variables CSS en un requestAnimationFrame.
 */
@Directive({ selector: '[appTilt]' })
export class TiltDirective implements OnInit {
  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;
  private readonly zone = inject(NgZone);
  private readonly destroyRef = inject(DestroyRef);

  /** Grados máximos de inclinación. */
  readonly tiltMax = input(7);

  ngOnInit(): void {
    if (!motionAllowed()) return;

    let frame = 0;
    const move = (event: PointerEvent) => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        const rect = this.el.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;
        const max = this.tiltMax();
        this.el.style.setProperty('--ry', `${(x - 0.5) * 2 * max}deg`);
        this.el.style.setProperty('--rx', `${(0.5 - y) * 2 * max}deg`);
        this.el.style.setProperty('--mx', `${x * 100}%`);
        this.el.style.setProperty('--my', `${y * 100}%`);
        this.el.classList.add('is-tilting');
      });
    };
    const leave = () => {
      cancelAnimationFrame(frame);
      this.el.classList.remove('is-tilting');
      this.el.style.setProperty('--rx', '0deg');
      this.el.style.setProperty('--ry', '0deg');
    };

    this.zone.runOutsideAngular(() => {
      this.el.addEventListener('pointermove', move, { passive: true });
      this.el.addEventListener('pointerleave', leave, { passive: true });
    });
    this.destroyRef.onDestroy(() => {
      cancelAnimationFrame(frame);
      this.el.removeEventListener('pointermove', move);
      this.el.removeEventListener('pointerleave', leave);
    });
  }
}
