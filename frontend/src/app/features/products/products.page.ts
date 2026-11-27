import { CurrencyPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { filter, switchMap } from 'rxjs';
import { AuthStore } from '../../core/auth/auth.store';
import { DialogService } from '../../core/ui/dialog.service';
import { ToastService } from '../../core/ui/toast.service';
import { CanDirective } from '../../shared/directives/can.directive';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { ExportMenuComponent } from '../../shared/ui/export-menu.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { PaginatorComponent } from '../../shared/ui/paginator.component';
import { SearchInputComponent } from '../../shared/ui/search-input.component';
import { SortHeaderComponent } from '../../shared/ui/sort-header.component';
import { EmptyStateComponent, PageHeaderComponent, TableSkeletonComponent } from '../../shared/ui/states.component';
import { createListController } from '../../shared/utils/list-controller';
import { ProductDetailDialog } from './product-detail.dialog';
import { ProductFormDialog } from './product-form.dialog';
import { Product, ProductsApi } from './products.api';

/**
 * Consulta de productos: Código, Nombre, Precio, Fecha de creación
 * + acciones (Ver / Editar / Eliminar) según permisos + exportar PDF/Excel.
 */
@Component({
  selector: 'app-products-page',
  imports: [
    CurrencyPipe, AppDatePipe, CanDirective, IconComponent, PaginatorComponent, SearchInputComponent,
    SortHeaderComponent, PageHeaderComponent, EmptyStateComponent, TableSkeletonComponent, ExportMenuComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './products.page.html',
})
export class ProductsPage {
  private readonly api = inject(ProductsApi);
  private readonly dialogs = inject(DialogService);
  private readonly toast = inject(ToastService);
  protected readonly store = inject(AuthStore);

  protected readonly list = createListController((params) => this.api.list(params));

  view(product: Product): void {
    this.dialogs.view(ProductDetailDialog, product).subscribe((action) => action === 'edit' && this.edit(product));
  }

  create(): void {
    this.dialogs.open<Product>(ProductFormDialog, null).pipe(filter(Boolean)).subscribe((p) => {
      this.toast.success('Producto creado', `${p.code} · ${p.name}`);
      this.list.reload();
    });
  }

  edit(product: Product): void {
    this.dialogs.open<Product>(ProductFormDialog, product).pipe(filter(Boolean)).subscribe((p) => {
      this.toast.success('Producto actualizado', `${p.code} · ${p.name}`);
      this.list.reload();
    });
  }

  remove(product: Product): void {
    this.dialogs
      .confirm({
        title: '¿Eliminar producto?',
        message: `Se eliminará "${product.name}" (${product.code}). Quedará registrado en la bitácora con su información anterior.`,
        confirmLabel: 'Eliminar',
        danger: true,
      })
      .pipe(
        filter(Boolean),
        switchMap(() => this.api.delete(product.id)),
      )
      .subscribe(() => {
        this.toast.success('Producto eliminado', product.code);
        this.list.reload();
      });
  }
}
