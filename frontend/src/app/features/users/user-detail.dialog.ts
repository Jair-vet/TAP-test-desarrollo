import { DIALOG_DATA, DialogRef } from '@angular/cdk/dialog';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { CanDirective } from '../../shared/directives/can.directive';
import { TiltDirective } from '../../shared/directives/tilt.directive';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { PhonePipe } from '../../shared/pipes/phone.pipe';
import { IconComponent } from '../../shared/ui/icon.component';
import { ModalComponent, ModalFooterComponent } from '../../shared/ui/modal.component';
import { UsersApi } from './users.api';

/**
 * Detalle de usuario (requisito): Usuario, Nombre, Teléfono, Foto de perfil
 * y Lista de perfiles relacionados. En pantallas medianas o más grandes se
 * muestra a dos columnas: tarjeta de identidad (foto) y datos.
 */
@Component({
  selector: 'app-user-detail-dialog',
  imports: [ModalComponent, ModalFooterComponent, IconComponent, AppDatePipe, PhonePipe, CanDirective, TiltDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-modal title="Detalle de usuario" [subtitle]="user()?.code ?? 'Cargando…'" icon="user-round">
      @if (user(); as u) {
        <div class="grid gap-6 animate-fade-in md:grid-cols-[17rem_1fr]">
          <!-- Tarjeta de identidad -->
          <div class="tilt spotlight relative flex flex-col items-center overflow-hidden rounded-2xl bg-ink-1000 px-5 pb-6 pt-7 text-center text-white ring-1 ring-white/10"
            appTilt [tiltMax]="6">
            <div class="pointer-events-none absolute inset-0 bg-grid opacity-40"></div>
            <div class="pointer-events-none absolute -top-16 h-40 w-40 rounded-full bg-brand-400/30 blur-3xl"></div>
            @if (u.photo_url) {
              <img [src]="u.photo_url" [alt]="'Foto de ' + u.name"
                class="tilt-pop relative h-32 w-32 rounded-full object-cover shadow-2xl ring-4 ring-brand-400/60 animate-scale-in" />
            }
            <h3 class="tilt-pop relative mt-4 text-lg font-bold leading-tight">{{ u.name }}</h3>
            <p class="relative mt-0.5 max-w-full truncate text-sm text-zinc-400">{{ u.email }}</p>
            <span class="code-chip relative mt-3 !bg-white/10 !text-brand-300">{{ u.code }}</span>
            <div class="relative mt-4 flex flex-wrap justify-center gap-2">
              @if (u.two_factor_enabled) { <span class="badge badge-green"><app-icon name="shield-check" [size]="12" /> 2FA activo</span> }
              @else { <span class="badge !bg-white/10 !text-zinc-300"><app-icon name="shield-off" [size]="12" /> Sin 2FA</span> }
              @if (u.must_change_password) { <span class="badge badge-brand">Cambio de contraseña pendiente</span> }
            </div>
          </div>

          <!-- Datos -->
          <div class="min-w-0">
            <dl class="divide-y divide-zinc-100 rounded-2xl border border-zinc-200 text-sm dark:divide-white/[0.06] dark:border-white/[0.07]">
              <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="flex items-center gap-2 text-zinc-500"><app-icon name="mail" [size]="15" /> Usuario</dt>
                <dd class="truncate font-medium">{{ u.email }}</dd>
              </div>
              <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="flex items-center gap-2 text-zinc-500"><app-icon name="user-round" [size]="15" /> Nombre</dt>
                <dd class="truncate font-medium">{{ u.name }}</dd>
              </div>
              <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="flex items-center gap-2 text-zinc-500"><app-icon name="phone" [size]="15" /> Teléfono</dt>
                <dd class="font-medium tabular-nums">{{ u.phone ? (u.phone | phone) : 'No registrado' }}</dd>
              </div>
              <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="flex items-center gap-2 text-zinc-500"><app-icon name="calendar" [size]="15" /> Fecha de creación</dt>
                <dd class="font-medium tabular-nums">{{ u.created_at | appDate }}</dd>
              </div>
              <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="flex items-center gap-2 text-zinc-500"><app-icon name="clock" [size]="15" /> Último acceso</dt>
                <dd class="font-medium tabular-nums">{{ u.last_login_at | appDate }}</dd>
              </div>
            </dl>

            <h4 class="mb-2 mt-5 flex items-center gap-2 text-sm font-semibold text-zinc-800 dark:text-zinc-200">
              <app-icon name="shield-check" [size]="16" /> Perfiles relacionados ({{ u.profiles.length }})
            </h4>
            <ul class="grid gap-2 sm:grid-cols-2">
              @for (p of u.profiles; track p.id; let i = $index) {
                <li class="tone-profiles tone-bar flex items-center justify-between gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm animate-fade-up dark:border-white/[0.07]"
                  [style.animation-delay.ms]="i * 60">
                  <span class="flex items-center gap-2 font-medium"><app-icon name="shield-check" [size]="15" class="text-violet-500" /> {{ p.name }}</span>
                  <span class="code-chip">{{ p.code }}</span>
                </li>
              } @empty {
                <li class="rounded-xl border border-dashed border-zinc-300 px-4 py-3 text-center text-sm text-zinc-500 sm:col-span-2 dark:border-white/10">Sin perfiles asignados</li>
              }
            </ul>
          </div>
        </div>
      } @else {
        <div class="grid gap-6 md:grid-cols-[17rem_1fr]">
          <div class="h-72 skeleton"></div>
          <div class="space-y-3"><div class="h-40 skeleton"></div><div class="h-20 skeleton"></div></div>
        </div>
      }
      <app-modal-footer>
        <button type="button" class="btn btn-outline" (click)="ref.close()">Cerrar</button>
        <button *appCan="'users:update'" type="button" class="btn btn-primary" (click)="ref.close('edit')" [disabled]="!user()">
          <app-icon name="pencil" [size]="16" /> Editar
        </button>
      </app-modal-footer>
    </app-modal>
  `,
})
export class UserDetailDialog {
  protected readonly ref = inject<DialogRef<'edit'>>(DialogRef);
  private readonly id = inject<string>(DIALOG_DATA);
  protected readonly user = toSignal(inject(UsersApi).get(this.id));
}
