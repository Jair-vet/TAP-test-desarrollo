import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { IconComponent } from './icon.component';

/** Estado vacío / sin resultados / error, con animación suave. */
@Component({
  selector: 'app-empty-state',
  imports: [IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center animate-fade-up">
      <div class="grid h-14 w-14 place-items-center rounded-2xl"
        [class]="tone() === 'error' ? 'bg-red-50 text-red-600 dark:bg-red-500/10' : 'bg-brand-50 text-brand-700 dark:bg-brand-400/10 dark:text-brand-300'">
        <app-icon [name]="icon()" [size]="26" />
      </div>
      <div>
        <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ title() }}</p>
        @if (message()) { <p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ message() }}</p> }
      </div>
      @if (actionLabel()) {
        <button type="button" class="btn btn-outline btn-sm mt-1" (click)="action.emit()">{{ actionLabel() }}</button>
      }
    </div>
  `,
})
export class EmptyStateComponent {
  readonly icon = input('inbox');
  readonly title = input.required<string>();
  readonly message = input<string>();
  readonly actionLabel = input<string>();
  readonly tone = input<'neutral' | 'error'>('neutral');
  readonly action = output<void>();
}

/** Filas "esqueleto" mientras carga la tabla (percepción de rapidez). */
@Component({
  // Selector de atributo sobre <tbody>: las filas esqueleto deben ser hijos válidos de <table>
  // eslint-disable-next-line @angular-eslint/component-selector
  selector: 'tbody[appTableSkeleton]',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @for (row of rowsArr(); track $index) {
      <tr>
        @for (col of colsArr(); track $index) {
          <td><div class="skeleton h-4" [style.width.%]="$index === 0 ? 55 : 40 + (($index * 17 + row) % 50)"></div></td>
        }
      </tr>
    }
  `,
})
export class TableSkeletonComponent {
  readonly rows = input(6, { alias: 'appTableSkeleton' });
  readonly cols = input(5);
  protected rowsArr = () => Array.from({ length: this.rows() }, (_, i) => i);
  protected colsArr = () => Array.from({ length: this.cols() });
}

/** Encabezado de página: título, descripción y acciones (ng-content). */
@Component({
  selector: 'app-page-header',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between animate-fade-up">
      <div>
        @if (eyebrow()) { <p class="mb-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">{{ eyebrow() }}</p> }
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-[1.7rem]">{{ title() }}</h1>
        @if (subtitle()) { <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ subtitle() }}</p> }
      </div>
      <div class="flex flex-wrap items-center gap-2"><ng-content /></div>
    </header>
  `,
})
export class PageHeaderComponent {
  readonly title = input.required<string>();
  readonly subtitle = input<string>();
  readonly eyebrow = input<string>();
}

/** Avatar: foto (URL firmada) o iniciales. */
@Component({
  selector: 'app-avatar',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (src()) {
      <img [src]="src()" [alt]="'Foto de ' + name()" loading="lazy" decoding="async"
        class="h-full w-full rounded-full object-cover ring-2 ring-white dark:ring-ink-900" />
    } @else {
      <span class="grid h-full w-full place-items-center rounded-full bg-ink-950 text-xs font-bold text-brand-400 dark:bg-brand-400 dark:text-ink-950">
        {{ initials() }}
      </span>
    }
  `,
  host: { class: 'inline-block shrink-0', '[style.width.px]': 'size()', '[style.height.px]': 'size()' },
})
export class AvatarComponent {
  readonly src = input<string | null>(null);
  readonly name = input('');
  readonly size = input(36);
  protected initials = () =>
    this.name()
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((p) => p[0]?.toUpperCase())
      .join('') || 'U';
}
