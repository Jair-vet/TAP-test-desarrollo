import { DIALOG_DATA, DialogRef } from '@angular/cdk/dialog';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { IconComponent } from './icon.component';

/** Textos y estilo del diálogo de confirmación (p. ej. antes de eliminar). */
export interface ConfirmOptions {
  title: string;
  message: string;
  confirmLabel?: string;
  danger?: boolean;
}

/**
 * Diálogo modal de confirmación. Se abre con DialogService.confirm() y
 * devuelve true/false al cerrarse.
 */
@Component({
  selector: 'app-confirm-dialog',
  imports: [IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="card w-full max-w-md p-6">
      <div class="flex gap-4">
        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full"
          [class]="data.danger ? 'bg-red-100 text-red-600 dark:bg-red-500/15' : 'bg-brand-100 text-brand-700'">
          <app-icon [name]="data.danger ? 'triangle-exclamation-point' : 'info'" [size]="20" />
        </div>
        <div>
          <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ data.title }}</h2>
          <p class="mt-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ data.message }}</p>
        </div>
      </div>
      <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="btn btn-outline" (click)="ref.close(false)">Cancelar</button>
        <button type="button" class="btn" [class]="data.danger ? 'btn-danger' : 'btn-primary'" (click)="ref.close(true)" cdkFocusInitial>
          {{ data.confirmLabel ?? 'Confirmar' }}
        </button>
      </div>
    </div>
  `,
})
export class ConfirmDialogComponent {
  protected readonly ref = inject<DialogRef<boolean>>(DialogRef);
  protected readonly data = inject<ConfirmOptions>(DIALOG_DATA);
}
