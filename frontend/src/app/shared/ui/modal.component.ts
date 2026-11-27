import { DialogRef } from '@angular/cdk/dialog';
import { ChangeDetectionStrategy, Component, inject, input } from '@angular/core';
import { IconComponent } from './icon.component';

/**
 * Carcasa visual de los modales (CDK Dialog se encarga de overlay, foco
 * atrapado, Esc para cerrar y aria-modal). Uso dentro de un componente
 * abierto con `Dialog.open(...)`:
 *   <app-modal title="Nuevo producto" icon="package"> ...cuerpo... <app-modal-footer>...</app-modal-footer></app-modal>
 */
@Component({
  selector: 'app-modal',
  imports: [IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="card flex max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-hidden shadow-2xl shadow-black/20">
      <header class="flex items-start gap-3 border-b border-zinc-100 px-6 py-5 dark:border-white/[0.06]">
        @if (icon()) {
          <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-400 text-ink-950 shadow-sm shadow-brand-600/30">
            <app-icon [name]="icon()!" [size]="19" />
          </div>
        }
        <div class="min-w-0 flex-1">
          <h2 class="text-lg font-semibold leading-tight text-zinc-900 dark:text-white">{{ title() }}</h2>
          @if (subtitle()) { <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400">{{ subtitle() }}</p> }
        </div>
        <button type="button" class="btn btn-ghost btn-icon -mr-2 -mt-1" (click)="ref.close()" aria-label="Cerrar">
          <app-icon name="x" [size]="18" />
        </button>
      </header>
      <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5"><ng-content /></div>
      <ng-content select="app-modal-footer" />
    </section>
  `,
})
export class ModalComponent {
  protected readonly ref = inject(DialogRef);
  readonly title = input.required<string>();
  readonly subtitle = input<string>();
  readonly icon = input<string>();
}

/** Pie estándar de modal (botones alineados a la derecha). */
@Component({
  selector: 'app-modal-footer',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <footer class="flex flex-col-reverse gap-2 border-t border-zinc-100 bg-zinc-50/70 px-6 py-4 sm:flex-row sm:justify-end dark:border-white/[0.06] dark:bg-white/[0.02]">
      <ng-content />
    </footer>
  `,
})
export class ModalFooterComponent {}
