import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IconComponent } from '../../shared/ui/icon.component';

/** Pantalla "Sin acceso": el perfil del usuario no incluye la sección pedida (guard de permisos). */
@Component({
  selector: 'app-forbidden-page',
  imports: [RouterLink, IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto flex max-w-md flex-col items-center py-20 text-center animate-fade-up">
      <div class="grid h-16 w-16 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10"><app-icon name="lock" [size]="28" /></div>
      <h1 class="mt-5 text-2xl font-bold">Sin acceso a esta sección</h1>
      <p class="mt-2 text-sm text-zinc-500">Tus perfiles no incluyen esta sección. Si la necesitas, pídela al administrador.</p>
      <a routerLink="/" class="btn btn-dark mt-6">Volver al inicio</a>
    </div>
  `,
})
export class ForbiddenPage {}

/** Pantalla 404 para rutas que no existen. */
@Component({
  selector: 'app-not-found-page',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="grid min-h-dvh place-items-center bg-ink-950 px-6 text-center text-white">
      <div class="animate-fade-up">
        <p class="text-8xl font-black tracking-tighter text-brand-400">404</p>
        <h1 class="mt-2 text-xl font-semibold">Página no encontrada</h1>
        <p class="mt-2 text-sm text-zinc-400">La dirección no existe o fue movida.</p>
        <a routerLink="/" class="btn btn-primary mt-6">Ir al inicio</a>
      </div>
    </div>
  `,
})
export class NotFoundPage {}
