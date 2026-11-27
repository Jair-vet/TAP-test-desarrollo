import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { SortDirection } from '../../core/models/api.models';

/**
 * Encabezado de columna ordenable (accesible: aria-sort).
 * Indicador doble ▲▼: la dirección activa se ilumina en amarillo; la inactiva
 * queda tenue. Sin ordenar, ambos tenues (se resaltan al pasar el mouse).
 */
@Component({
  // Selector de atributo sobre <th>: conserva la semántica de tabla y permite aria-sort en la celda
  // eslint-disable-next-line @angular-eslint/component-selector
  selector: 'th[appSortHeader]',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '[attr.aria-sort]': 'ariaSort()' },
  template: `
    <button type="button" (click)="sortChange.emit(column())"
      class="group -mx-1 inline-flex items-center gap-2 rounded-md px-1 py-0.5 uppercase tracking-wider transition-colors hover:text-zinc-900 dark:hover:text-white"
      [class]="active() ? 'text-zinc-900 dark:text-white' : ''">
      <ng-content />
      <span class="flex flex-col gap-[2px]" aria-hidden="true">
        <svg viewBox="0 0 8 5" class="h-[5px] w-2 transition-all duration-300"
          [class]="up() ? 'fill-brand-500 drop-shadow-[0_0_3px_rgba(255,212,0,0.8)]' : 'fill-zinc-300 group-hover:fill-zinc-400 dark:fill-zinc-600'">
          <path d="M4 0 8 5H0z" />
        </svg>
        <svg viewBox="0 0 8 5" class="h-[5px] w-2 transition-all duration-300"
          [class]="down() ? 'fill-brand-500 drop-shadow-[0_0_3px_rgba(255,212,0,0.8)]' : 'fill-zinc-300 group-hover:fill-zinc-400 dark:fill-zinc-600'">
          <path d="M0 0h8L4 5z" />
        </svg>
      </span>
    </button>
  `,
})
export class SortHeaderComponent {
  readonly column = input.required<string>({ alias: 'appSortHeader' });
  readonly sort = input.required<string>();
  readonly direction = input.required<SortDirection>();
  readonly sortChange = output<string>();

  protected readonly active = computed(() => this.sort() === this.column());
  protected readonly up = computed(() => this.active() && this.direction() === 'asc');
  protected readonly down = computed(() => this.active() && this.direction() === 'desc');
  protected readonly ariaSort = computed(() => (!this.active() ? 'none' : this.direction() === 'asc' ? 'ascending' : 'descending'));
}
