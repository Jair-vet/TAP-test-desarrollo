import { DIALOG_DATA, DialogRef } from '@angular/cdk/dialog';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { CanDirective } from '../../shared/directives/can.directive';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { IconComponent } from '../../shared/ui/icon.component';
import { ModalComponent, ModalFooterComponent } from '../../shared/ui/modal.component';
import { actionMeta, sectionMeta } from '../../shared/utils/sections';
import { ProfilesApi } from './profiles.api';

/**
 * Detalle del perfil (modal, como pide el examen): código, nombre, fecha de
 * creación y lista de secciones relacionadas, cada una con su color y acciones.
 */
@Component({
  selector: 'app-profile-detail-dialog',
  imports: [ModalComponent, ModalFooterComponent, IconComponent, AppDatePipe, CanDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-modal title="Detalle del perfil" [subtitle]="profile()?.name ?? 'Cargando…'" icon="shield-check">
      @if (profile(); as p) {
        <div class="animate-fade-in">
          <dl class="grid grid-cols-2 gap-3 text-sm lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/[0.07]">
              <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><app-icon name="hash" [size]="13" /> Código de perfil</dt>
              <dd class="mt-1 font-mono font-semibold">{{ p.code }}</dd>
            </div>
            <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/[0.07]">
              <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><app-icon name="tag" [size]="13" /> Nombre de perfil</dt>
              <dd class="mt-1 truncate font-semibold">{{ p.name }}</dd>
            </div>
            <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/[0.07]">
              <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><app-icon name="calendar" [size]="13" /> Fecha de creación</dt>
              <dd class="mt-1 font-semibold tabular-nums">{{ p.created_at | appDate }}</dd>
            </div>
            <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/[0.07]">
              <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><app-icon name="users" [size]="13" /> Usuarios asignados</dt>
              <dd class="mt-1 font-semibold">{{ p.users_count ?? 0 }}</dd>
            </div>
          </dl>
          @if (p.description) { <p class="mt-4 rounded-xl bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:bg-white/[0.03] dark:text-zinc-400">{{ p.description }}</p> }

          <h4 class="mb-3 mt-6 flex items-center gap-2 text-sm font-semibold"><app-icon name="layers" [size]="16" /> Secciones relacionadas ({{ p.sections.length }})</h4>
          <ul class="grid gap-3 sm:grid-cols-2">
            @for (s of p.sections; track s.key; let i = $index) {
              <li class="tone-bar rounded-2xl border border-zinc-200 p-4 animate-fade-up dark:border-white/[0.07]"
                [class]="meta(s.key).tone" [style.animation-delay.ms]="i * 70">
                <div class="flex items-center gap-3">
                  <span class="tone-tile h-10 w-10 shrink-0"><app-icon [name]="meta(s.key).icon" [size]="18" /></span>
                  <div>
                    <p class="font-semibold">{{ s.label }}</p>
                    <p class="text-xs text-zinc-500">{{ s.actions.length }} acción(es)</p>
                  </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                  @for (a of s.actions; track a) {
                    <span class="tone-chip" [class]="action(a).tone"><app-icon [name]="action(a).icon" [size]="12" /> {{ action(a).label }}</span>
                  }
                </div>
              </li>
            }
          </ul>
        </div>
      } @else {
        <div class="space-y-3"><div class="h-20 skeleton"></div><div class="h-28 skeleton"></div></div>
      }
      <app-modal-footer>
        <button type="button" class="btn btn-outline" (click)="ref.close()">Cerrar</button>
        <button *appCan="'profiles:update'" type="button" class="btn btn-primary" (click)="ref.close('edit')" [disabled]="!profile()">
          <app-icon name="pencil" [size]="16" /> Editar
        </button>
      </app-modal-footer>
    </app-modal>
  `,
})
export class ProfileDetailDialog {
  protected readonly ref = inject<DialogRef<'edit'>>(DialogRef);
  private readonly id = inject<string>(DIALOG_DATA);
  protected readonly profile = toSignal(inject(ProfilesApi).get(this.id));
  protected readonly meta = sectionMeta;
  protected readonly action = actionMeta;
}
