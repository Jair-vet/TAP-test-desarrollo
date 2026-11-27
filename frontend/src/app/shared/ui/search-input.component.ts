import { ChangeDetectionStrategy, Component, input, model } from '@angular/core';
import { IconComponent } from './icon.component';

/**
 * Caja de búsqueda de las tablas con botón para limpiar.
 * Enlace bidireccional: [(value)]; el debounce y la paginación los aplica ListController.
 */
@Component({
  selector: 'app-search-input',
  imports: [IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <label class="group relative block w-full sm:w-80">
      <span class="sr-only">{{ placeholder() }}</span>
      <app-icon name="search" [size]="17"
        class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400 transition-colors group-focus-within:text-brand-600" />
      <input
        type="search"
        class="input pl-10 pr-9"
        [placeholder]="placeholder()"
        [value]="value()"
        (input)="value.set($any($event.target).value)"
        maxlength="80"
        autocomplete="off"
      />
      @if (value()) {
        <button type="button" (click)="value.set('')" aria-label="Limpiar búsqueda"
          class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/10 animate-fade-in">
          <app-icon name="x" [size]="15" />
        </button>
      }
    </label>
  `,
})
export class SearchInputComponent {
  readonly value = model('');
  readonly placeholder = input('Buscar…');
}
