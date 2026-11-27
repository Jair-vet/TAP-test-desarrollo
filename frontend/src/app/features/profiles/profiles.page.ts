import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { filter, switchMap } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
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
import { SECTION_META, sectionMeta } from '../../shared/utils/sections';
import { SectionKey } from '../../core/models/api.models';
import { UsersApi } from '../users/users.api';
import { ProfileDetailDialog } from './profile-detail.dialog';
import { ProfileFormDialog } from './profile-form.dialog';
import { Profile, ProfilesApi } from './profiles.api';

/** Perfiles de usuario: Código, Nombre, Fecha de creación + acciones. */
@Component({
  selector: 'app-profiles-page',
  imports: [
    AppDatePipe, CanDirective, IconComponent, PaginatorComponent, SearchInputComponent, SortHeaderComponent,
    PageHeaderComponent, EmptyStateComponent, TableSkeletonComponent, ExportMenuComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './profiles.page.html',
})
export class ProfilesPage {
  private readonly api = inject(ProfilesApi);
  private readonly usersApi = inject(UsersApi);
  private readonly dialogs = inject(DialogService);
  private readonly toast = inject(ToastService);
  private readonly auth = inject(AuthService);

  protected readonly list = createListController((params) => this.api.list(params));
  protected readonly sections = (Object.keys(SECTION_META) as SectionKey[]).map((key) => ({ key, meta: SECTION_META[key] }));
  protected readonly meta = sectionMeta;

  detail(profile: Profile): void {
    this.dialogs.view<'edit'>(ProfileDetailDialog, profile.id, 'lg').subscribe((a) => a === 'edit' && this.edit(profile));
  }

  create(): void {
    this.dialogs.open<Profile>(ProfileFormDialog, null, { size: 'lg' }).pipe(filter(Boolean)).subscribe((p) => this.saved('Perfil creado', p));
  }

  edit(profile: Profile): void {
    this.dialogs.open<Profile>(ProfileFormDialog, profile, { size: 'lg' }).pipe(filter(Boolean)).subscribe((p) => {
      this.saved('Perfil actualizado', p);
      // Si cambié un perfil que yo tengo, refresco mi sesión para ver los permisos nuevos
      this.auth.refresh().subscribe({ error: () => void 0 });
    });
  }

  remove(profile: Profile): void {
    this.dialogs
      .confirm({
        title: '¿Eliminar perfil?',
        message: `Se eliminará el perfil "${profile.name}". Si está asignado a usuarios, el sistema no lo permitirá.`,
        confirmLabel: 'Eliminar',
        danger: true,
      })
      .pipe(
        filter(Boolean),
        switchMap(() => this.api.delete(profile.id)),
      )
      .subscribe(() => this.saved('Perfil eliminado', profile));
  }

  private saved(title: string, profile: Profile): void {
    this.toast.success(title, `${profile.code} · ${profile.name}`);
    this.usersApi.invalidateProfileOptions();
    this.list.reload();
  }
}
