import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { LUCIDE_ICONS, LucideDynamicIcon } from '@lucide/angular';

/**
 * Envoltorio de íconos (Lucide). Si mañana cambian la librería de íconos,
 * solo se toca este componente y la lista de app.icons.ts.
 * Uso: <app-icon name="pencil" [size]="16" />
 *
 * Si el nombre no está registrado muestra "info" en lugar de lanzar error
 * (un ícono faltante nunca debe romper la pantalla).
 */
@Component({
  selector: 'app-icon',
  imports: [LucideDynamicIcon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'inline-flex shrink-0', 'aria-hidden': 'true' },
  template: `<svg [lucideIcon]="safeName()" [size]="size()" [strokeWidth]="strokeWidth()"></svg>`,
})
export class IconComponent {
  private readonly registry = inject(LUCIDE_ICONS, { optional: true }) ?? {};

  readonly name = input.required<string>();
  readonly size = input<number>(18);
  readonly strokeWidth = input<number>(2);

  protected readonly safeName = computed(() => {
    const name = this.name();
    if (name in this.registry) return name;
    console.warn(`[app-icon] Ícono no registrado: "${name}" (agrégalo en app.icons.ts)`);
    return 'info';
  });
}
