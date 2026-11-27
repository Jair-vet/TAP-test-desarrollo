import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { ParallaxDirective } from '../directives/parallax.directive';

interface Box {
  top: string;
  left: string;
  w: number;
  h: number;
  d: number;
  color: string;
  depth: number;
  delay: number;
}

/**
 * Escena decorativa: contenedores marítimos en 3D (CSS puro) flotando con
 * parallax por capas. Se usa en el login y en el encabezado del inicio.
 * Es decorativa: aria-hidden y sin interacción.
 */
@Component({
  selector: 'app-port-scene',
  imports: [ParallaxDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'pointer-events-none absolute inset-0 overflow-hidden', 'aria-hidden': 'true' },
  template: `
    <div class="absolute inset-0 bg-grid opacity-60" appParallax [parallaxDepth]="6"></div>
    <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-400/25 blur-3xl" appParallax [parallaxDepth]="18"></div>
    <div class="absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-brand-400/10 blur-3xl" appParallax [parallaxDepth]="10"></div>

    @for (b of boxes(); track $index) {
      <div class="absolute" [style.top]="b.top" [style.left]="b.left" appParallax [parallaxDepth]="b.depth">
        <div class="animate-float" [style.animation-delay.s]="b.delay">
          <div class="container3d" [style.--w]="b.w + 'px'" [style.--h]="b.h + 'px'" [style.--d]="b.d + 'px'" [style.--c]="b.color">
            <i></i><i></i><i></i><i></i>
          </div>
        </div>
      </div>
    }
  `,
})
export class PortSceneComponent {
  /** 'login' = escena completa; 'compact' = pocos contenedores (encabezados). */
  readonly variant = input<'login' | 'compact'>('login');

  protected readonly boxes = computed<Box[]>(() => {
    if (this.variant() === 'compact') {
      return [
        { top: '14%', left: '72%', w: 110, h: 48, d: 48, color: '#ffd400', depth: 34, delay: 0 },
        { top: '54%', left: '84%', w: 90, h: 40, d: 40, color: '#2b2b31', depth: 22, delay: 1.2 },
        { top: '62%', left: '64%', w: 70, h: 32, d: 32, color: '#e6bf00', depth: 14, delay: 2.1 },
      ];
    }
    return [
      { top: '16%', left: '58%', w: 150, h: 64, d: 64, color: '#ffd400', depth: 42, delay: 0 },
      { top: '30%', left: '74%', w: 120, h: 52, d: 52, color: '#2b2b31', depth: 30, delay: 1.4 },
      { top: '44%', left: '52%', w: 110, h: 48, d: 48, color: '#e6bf00', depth: 24, delay: 0.7 },
      { top: '8%', left: '82%', w: 90, h: 40, d: 40, color: '#3a3a42', depth: 18, delay: 2.2 },
      { top: '52%', left: '80%', w: 80, h: 36, d: 36, color: '#ffd400', depth: 12, delay: 3 },
    ];
  });
}
