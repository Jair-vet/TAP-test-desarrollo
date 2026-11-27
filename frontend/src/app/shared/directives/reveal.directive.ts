import { DestroyRef, Directive, ElementRef, OnInit, inject, input } from '@angular/core';
import { prefersReducedMotion } from './motion';

/**
 * Aparición suave cuando el elemento entra a la pantalla (IntersectionObserver,
 * sin listeners de scroll). Se anima una sola vez.
 *   <section appReveal [revealDelay]="120">…</section>
 */
@Directive({
  selector: '[appReveal]',
  host: { class: 'reveal' },
})
export class RevealDirective implements OnInit {
  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;
  private readonly destroyRef = inject(DestroyRef);

  readonly revealDelay = input(0);

  ngOnInit(): void {
    this.el.style.setProperty('--reveal-delay', `${this.revealDelay()}ms`);

    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
      this.el.classList.add('is-visible');
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting)) {
          this.el.classList.add('is-visible');
          observer.disconnect();
        }
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );
    observer.observe(this.el);
    this.destroyRef.onDestroy(() => observer.disconnect());
  }
}
