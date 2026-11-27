import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthStore } from '../../core/auth/auth.store';
import { FormErrorComponent } from '../../shared/ui/form-error.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { notBlank } from '../../shared/utils/validators';
import { AuthLayoutComponent } from '../auth/auth-layout.component';
import { MailboxService } from './mailbox.service';

/**
 * Acceso a la bandeja de correos de prueba con el diseño del sistema
 * (antes: diálogo nativo de autenticación básica del navegador).
 */
@Component({
  selector: 'app-mailbox-login-page',
  imports: [ReactiveFormsModule, RouterLink, AuthLayoutComponent, IconComponent, FormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-auth-layout title="Bandeja de correos" subtitle="Aquí llegan los correos que envía el sistema (credenciales de alta y recuperación). Es un entorno de prueba.">
      <div class="mb-6 flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-500/25 dark:bg-sky-500/10 dark:text-sky-200">
        <app-icon name="mail" [size]="18" class="mt-0.5 shrink-0" />
        <p>Los datos de acceso están en el archivo <code class="font-mono text-xs">.env</code> del proyecto
          (<code class="font-mono text-xs">MAILPIT_UI_USER</code> / <code class="font-mono text-xs">MAILPIT_UI_PASSWORD</code>) o con <code class="font-mono text-xs">make credentials</code>.</p>
      </div>

      @if (canUseSession) {
        <button type="button" class="btn btn-primary mb-5 h-11 w-full" (click)="useSession()" [disabled]="loading()">
          <app-icon name="shield-check" [size]="17" /> Entrar con mi sesión actual
        </button>
        <div class="mb-5 flex items-center gap-3 text-xs text-zinc-400"><span class="h-px flex-1 bg-zinc-200 dark:bg-white/10"></span> o con credenciales <span class="h-px flex-1 bg-zinc-200 dark:bg-white/10"></span></div>
      }

      <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-5" novalidate>
        <div>
          <label class="label" for="mb-user">Usuario</label>
          <div class="relative">
            <app-icon name="user-round" [size]="17" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400" />
            <input id="mb-user" class="input pl-10" formControlName="user" autocomplete="username" maxlength="60" />
          </div>
          <app-form-error [control]="form.controls.user" />
        </div>
        <div>
          <label class="label" for="mb-pass">Contraseña</label>
          <div class="relative">
            <app-icon name="lock" [size]="17" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400" />
            <input id="mb-pass" type="password" class="input pl-10" formControlName="password" autocomplete="current-password" maxlength="200" />
          </div>
          <app-form-error [control]="form.controls.password" />
        </div>

        @if (error()) {
          <div class="flex items-center gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-700 animate-fade-in dark:bg-red-500/10 dark:text-red-300" role="alert">
            <app-icon name="circle-exclamation-point" [size]="17" /> {{ error() }}
          </div>
        }

        <button type="submit" class="btn btn-dark h-11 w-full" [disabled]="loading()">
          @if (loading()) { <app-icon name="loader" [size]="17" class="animate-spin" /> Verificando… }
          @else { Abrir bandeja <app-icon name="arrow-right" [size]="17" /> }
        </button>
        <a routerLink="/login" class="block text-center text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white">← Ir al inicio de sesión del sistema</a>
      </form>
    </app-auth-layout>
  `,
})
export class MailboxLoginPage {
  private readonly mailbox = inject(MailboxService);
  private readonly store = inject(AuthStore);

  /** ?next=/mail/... (a dónde volver tras entrar) */
  readonly next = input<string>();

  protected readonly canUseSession = this.store.can('users', 'create');
  protected readonly loading = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = inject(NonNullableFormBuilder).group({
    user: ['', [Validators.required, notBlank, Validators.maxLength(60)]],
    password: ['', [Validators.required, Validators.maxLength(200)]],
  });

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.loading.set(true);
    this.error.set(null);
    const { user, password } = this.form.getRawValue();
    this.mailbox.login(user.trim(), password).subscribe({
      next: () => (window.location.href = MailboxService.safeNext(this.next())),
      error: (e: unknown) => {
        this.loading.set(false);
        const status = e instanceof HttpErrorResponse ? e.status : 0;
        this.error.set(status === 429 ? 'Demasiados intentos. Espera un minuto.' : 'Usuario o contraseña incorrectos.');
      },
    });
  }

  /** Pantalla /correos: entra en la MISMA pestaña y vuelve a ?next (p. ej. /mail/). */
  useSession(): void {
    this.loading.set(true);
    this.error.set(null);
    this.mailbox.sessionPass().subscribe({
      next: () => (window.location.href = MailboxService.safeNext(this.next())),
      error: () => this.loading.set(false),
    });
  }
}
