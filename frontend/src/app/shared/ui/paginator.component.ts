import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { PageMeta } from '../../core/models/api.models';
import { IconComponent } from './icon.component';

@Component({
  selector: 'app-paginator',
  imports: [IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (meta(); as m) {
      <nav class="flex flex-col items-center justify-between gap-3 px-4 py-3 text-sm sm:flex-row" aria-label="Paginación">
        <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
          <span>
            @if (m.total === 0) { Sin resultados }
            @else { <b class="text-zinc-800 dark:text-zinc-200">{{ from() }}–{{ to() }}</b> de <b class="text-zinc-800 dark:text-zinc-200">{{ m.total }}</b> }
          </span>
          <label class="flex items-center gap-1.5">
            <span class="hidden sm:inline">Filas:</span>
            <select class="h-8 rounded-lg border border-zinc-300 bg-white px-2 text-sm dark:border-white/10 dark:bg-ink-900"
              [value]="m.per_page" (change)="perPageChange.emit(+$any($event.target).value)" aria-label="Filas por página">
              @for (size of sizes; track size) { <option [value]="size" [selected]="size === m.per_page">{{ size }}</option> }
            </select>
          </label>
        </div>

        <div class="flex items-center gap-1">
          <button type="button" class="btn btn-ghost btn-icon" [disabled]="m.page <= 1" (click)="pageChange.emit(1)" aria-label="Primera página">
            <app-icon name="chevrons-left" [size]="16" />
          </button>
          <button type="button" class="btn btn-ghost btn-icon" [disabled]="m.page <= 1" (click)="pageChange.emit(m.page - 1)" aria-label="Página anterior">
            <app-icon name="chevron-left" [size]="16" />
          </button>
          @for (p of pages(); track $index) {
            @if (p === 0) {
              <span class="px-1 text-zinc-400">…</span>
            } @else {
              <button type="button" (click)="pageChange.emit(p)" [attr.aria-current]="p === m.page ? 'page' : null"
                class="h-9 min-w-9 rounded-lg px-2 text-sm font-medium transition-all duration-200"
                [class]="p === m.page
                  ? 'bg-ink-950 text-brand-400 shadow-sm dark:bg-brand-400 dark:text-ink-950'
                  : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/[0.06]'">
                {{ p }}
              </button>
            }
          }
          <button type="button" class="btn btn-ghost btn-icon" [disabled]="m.page >= m.last_page" (click)="pageChange.emit(m.page + 1)" aria-label="Página siguiente">
            <app-icon name="chevron-right" [size]="16" />
          </button>
          <button type="button" class="btn btn-ghost btn-icon" [disabled]="m.page >= m.last_page" (click)="pageChange.emit(m.last_page)" aria-label="Última página">
            <app-icon name="chevrons-right" [size]="16" />
          </button>
        </div>
      </nav>
    }
  `,
})
export class PaginatorComponent {
  readonly meta = input<PageMeta | null>(null);
  readonly pageChange = output<number>();
  readonly perPageChange = output<number>();
  protected readonly sizes = [5, 10, 25, 50, 100];

  protected readonly from = computed(() => {
    const m = this.meta();
    return m ? (m.page - 1) * m.per_page + 1 : 0;
  });
  protected readonly to = computed(() => {
    const m = this.meta();
    return m ? Math.min(m.page * m.per_page, m.total) : 0;
  });

  /** Ventana de páginas: 1 … 4 5 [6] 7 8 … 20  (0 = separador) */
  protected readonly pages = computed(() => {
    const m = this.meta();
    if (!m) return [];
    const { page, last_page: last } = m;
    const set = new Set([1, last, page - 1, page, page + 1].filter((p) => p >= 1 && p <= last));
    const sorted = [...set].sort((a, b) => a - b);
    const result: number[] = [];
    sorted.forEach((p, i) => {
      if (i > 0 && p - sorted[i - 1] > 1) result.push(0);
      result.push(p);
    });
    return result;
  });
}
