import { DestroyRef, Directive, ElementRef, NgZone, OnInit, inject, input } from '@angular/core';
import { motionAllowed } from './motion';

/**
 * Parallax por profundidad: el elemento se desplaza según la posición del
 * mouse en la ventana. Mayor `parallaxDepth` = parece más cercano.
 *   <div appParallax [parallaxDepth]="24">…</div>
 *
 * Usa `translate` (propiedad independiente de `transform`), así no pisa las
 * transformaciones 3D propias del elemento.
 */
@Directive({ selector: '[appParallax]' })
export class ParallaxDirective implements OnInit {
  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;
  private readonly zone = inject(NgZone);
  private readonly destroyRef = inject(DestroyRef);

  /** Píxeles máximos de desplazamiento. */
  readonly parallaxDepth = input(16);

  ngOnInit(): void {
    if (!motionAllowed()) return;

    this.el.style.transition = 'translate 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
    let frame = 0;
    const move = (event: PointerEvent) => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        const x = event.clientX / window.innerWidth - 0.5;
        const y = event.clientY / window.innerHeight - 0.5;
        const depth = this.parallaxDepth();
        this.el.style.translate = `${(-x * depth).toFixed(1)}px ${(-y * depth).toFixed(1)}px`;
      });
    };

    this.zone.runOutsideAngular(() => window.addEventListener('pointermove', move, { passive: true }));
    this.destroyRef.onDestroy(() => {
      cancelAnimationFrame(frame);
      window.removeEventListener('pointermove', move);
    });
  }
}
