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
import { AvatarComponent, EmptyStateComponent, PageHeaderComponent, TableSkeletonComponent } from '../../shared/ui/states.component';
import { createListController } from '../../shared/utils/list-controller';
import { UserDetailDialog } from './user-detail.dialog';
import { UserFormDialog } from './user-form.dialog';
import { UserDetail, UserSummary, UsersApi } from './users.api';

/** Consulta de usuarios: Código, Usuario, Nombre, Fecha de creación + acciones. */
@Component({
  selector: 'app-users-page',
  imports: [
    AppDatePipe, CanDirective, IconComponent, PaginatorComponent, SearchInputComponent, SortHeaderComponent,
    PageHeaderComponent, EmptyStateComponent, TableSkeletonComponent, ExportMenuComponent, AvatarComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './users.page.html',
})
export class UsersPage {
  private readonly api = inject(UsersApi);
  private readonly dialogs = inject(DialogService);
  private readonly toast = inject(ToastService);
  protected readonly store = inject(AuthStore);

  protected readonly list = createListController((params) => this.api.list(params));

  detail(user: UserSummary): void {
    this.dialogs.view<'edit'>(UserDetailDialog, user.id, 'lg').subscribe((action) => action === 'edit' && this.edit(user));
  }

  create(): void {
    this.dialogs.open<UserDetail>(UserFormDialog, null, { size: 'lg' }).pipe(filter(Boolean)).subscribe((u) => {
      this.toast.success('Usuario creado', `Se enviaron las credenciales a ${u.email}`);
      this.list.reload();
    });
  }

  edit(user: UserSummary): void {
    this.api
      .get(user.id)
      .pipe(switchMap((detail) => this.dialogs.open<UserDetail>(UserFormDialog, detail, { size: 'lg' })), filter(Boolean))
      .subscribe((u) => {
        this.toast.success('Usuario actualizado', `${u.code} · ${u.name}`);
        if (u.id === this.store.user()?.id) this.store.patchUser({ name: u.name, email: u.email, photo_url: u.photo_url });
        this.list.reload();
      });
  }

  remove(user: UserSummary): void {
    this.dialogs
      .confirm({
        title: '¿Eliminar usuario?',
        message: `Se eliminará a ${user.name} (${user.email}) y se cerrarán sus sesiones.`,
        confirmLabel: 'Eliminar',
        danger: true,
      })
      .pipe(
        filter(Boolean),
        switchMap(() => this.api.delete(user.id)),
      )
      .subscribe(() => {
        this.toast.success('Usuario eliminado', user.email);
        this.list.reload();
      });
  }
}
