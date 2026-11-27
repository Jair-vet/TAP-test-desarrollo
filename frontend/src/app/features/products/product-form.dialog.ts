import { DIALOG_DATA, DialogRef } from '@angular/cdk/dialog';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { FormErrorComponent } from '../../shared/ui/form-error.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { ModalComponent, ModalFooterComponent } from '../../shared/ui/modal.component';
import { applyServerErrors } from '../../shared/utils/form-errors';
import { Product, ProductsApi } from './products.api';
import { notBlank, safeText } from '../../shared/utils/validators';

/** Precio: máximo 3 dígitos enteros y 2 decimales (igual que el backend). */
const PRICE_PATTERN = /^\d{1,3}(\.\d{1,2})?$/;

/**
 * Alta / edición de producto.
 * El código y la fecha de creación NO se capturan: los genera el servidor.
 */
@Component({
  selector: 'app-product-form-dialog',
  imports: [ReactiveFormsModule, ModalComponent, ModalFooterComponent, FormErrorComponent, IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-modal [title]="product ? 'Editar producto' : 'Nuevo producto'"
      [subtitle]="product ? product.code : 'El código y la fecha se generan automáticamente'"
      [icon]="product ? 'pencil' : 'package'">
      <form id="product-form" [formGroup]="form" (ngSubmit)="save()" class="space-y-5" novalidate>
        <div>
          <label class="label" for="name">Nombre <span class="text-red-500">*</span></label>
          <input id="name" cdkFocusInitial class="input" formControlName="name" maxlength="120" placeholder="Ej. Casco de seguridad clase E" />
          <app-form-error [control]="form.controls.name" />
        </div>

        <div>
          <label class="label" for="brand">Marca <span class="text-red-500">*</span></label>
          <input id="brand" class="input" formControlName="brand" maxlength="80" placeholder="Ej. 3M" />
          <app-form-error [control]="form.controls.brand" />
        </div>

        <div>
          <label class="label" for="price">Precio <span class="text-red-500">*</span></label>
          <div class="relative">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-zinc-400">$</span>
            <input id="price" class="input pl-8 tabular-nums" formControlName="price" inputmode="decimal" maxlength="6" placeholder="0.00" (input)="sanitizePrice()" />
          </div>
          <p class="hint">Máximo 3 dígitos enteros (hasta $999.99).</p>
          <app-form-error [control]="form.controls.price" />
        </div>

        @if (error()) {
          <div class="flex items-center gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-700 animate-fade-in dark:bg-red-500/10 dark:text-red-300" role="alert">
            <app-icon name="circle-exclamation-point" [size]="17" /> {{ error() }}
          </div>
        }
      </form>

      <app-modal-footer>
        <button type="button" class="btn btn-outline" (click)="ref.close()">Cancelar</button>
        <button type="submit" form="product-form" class="btn btn-primary" [disabled]="saving()">
          @if (saving()) { <app-icon name="loader" [size]="16" class="animate-spin" /> Guardando… }
          @else { <app-icon name="circle-check" [size]="16" /> {{ product ? 'Guardar cambios' : 'Crear producto' }} }
        </button>
      </app-modal-footer>
    </app-modal>
  `,
})
export class ProductFormDialog {
  protected readonly ref = inject<DialogRef<Product>>(DialogRef);
  protected readonly product = inject<Product | null>(DIALOG_DATA);
  private readonly api = inject(ProductsApi);

  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = inject(NonNullableFormBuilder).group({
    name: [this.product?.name ?? '', [Validators.required, notBlank, Validators.minLength(2), Validators.maxLength(120), safeText]],
    brand: [this.product?.brand ?? '', [Validators.required, notBlank, Validators.maxLength(80), safeText]],
    price: [this.product ? this.product.price.toFixed(2) : '', [Validators.required, Validators.pattern(PRICE_PATTERN), Validators.min(0.01)]],
  });

  /** Solo dígitos y un punto; corta a 3 enteros + 2 decimales mientras se escribe. */
  sanitizePrice(): void {
    const control = this.form.controls.price;
    const clean = control.value.replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1');
    const [int = '', dec] = clean.split('.');
    const next = dec !== undefined ? `${int.slice(0, 3)}.${dec.slice(0, 2)}` : int.slice(0, 3);
    if (next !== control.value) control.setValue(next);
  }

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    this.error.set(null);
    const value = this.form.getRawValue();
    const request$ = this.product ? this.api.update(this.product.id, value) : this.api.create(value);

    request$.subscribe({
      next: (saved) => this.ref.close(saved),
      error: (e: unknown) => {
        this.saving.set(false);
        this.error.set(applyServerErrors(this.form, e));
      },
    });
  }
}
