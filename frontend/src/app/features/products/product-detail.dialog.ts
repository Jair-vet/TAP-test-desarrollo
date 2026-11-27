import { DIALOG_DATA, DialogRef } from '@angular/cdk/dialog';
import { CurrencyPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { CanDirective } from '../../shared/directives/can.directive';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { IconComponent } from '../../shared/ui/icon.component';
import { ModalComponent, ModalFooterComponent } from '../../shared/ui/modal.component';
import { Product } from './products.api';

/** Visualización de un producto (solo lectura). */
@Component({
  selector: 'app-product-detail-dialog',
  imports: [ModalComponent, ModalFooterComponent, IconComponent, AppDatePipe, CurrencyPipe, CanDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-modal [title]="p.name" [subtitle]="p.brand" icon="package">
      <div class="mb-5 rounded-2xl bg-gradient-to-br from-ink-950 to-ink-800 p-5 text-white">
        <p class="text-xs uppercase tracking-[0.18em] text-zinc-400">Precio</p>
        <p class="mt-1 text-3xl font-bold tabular-nums text-brand-400">{{ p.price | currency: 'MXN' : 'symbol-narrow' : '1.2-2' }}</p>
      </div>
      <dl class="grid grid-cols-2 gap-4 text-sm">
        <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/[0.06]">
          <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><app-icon name="hash" [size]="13" /> Código</dt>
          <dd class="mt-1 font-mono font-semibold">{{ p.code }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/[0.06]">
          <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><app-icon name="tag" [size]="13" /> Marca</dt>
          <dd class="mt-1 font-semibold">{{ p.brand }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/[0.06]">
          <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><app-icon name="calendar" [size]="13" /> Creado</dt>
          <dd class="mt-1 font-semibold tabular-nums">{{ p.created_at | appDate }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/[0.06]">
          <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><app-icon name="clock" [size]="13" /> Última edición</dt>
          <dd class="mt-1 font-semibold tabular-nums">{{ p.updated_at | appDate }}</dd>
        </div>
      </dl>
      <app-modal-footer>
        <button type="button" class="btn btn-outline" (click)="ref.close()">Cerrar</button>
        <button *appCan="'products:update'" type="button" class="btn btn-primary" (click)="ref.close('edit')">
          <app-icon name="pencil" [size]="16" /> Editar
        </button>
      </app-modal-footer>
    </app-modal>
  `,
})
export class ProductDetailDialog {
  protected readonly ref = inject<DialogRef<'edit'>>(DialogRef);
  protected readonly p = inject<Product>(DIALOG_DATA);
}
