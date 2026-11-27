import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { FormErrorComponent } from '../../shared/ui/form-error.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { AuthLayoutComponent } from './auth-layout.component';
import { strictEmail } from '../../shared/utils/validators';

/**
 * Recuperación de contraseña (requisito del examen):
 *  - valida que el usuario exista (el backend responde 404 si no)
 *  - envía una contraseña temporal al correo registrado
 */
@Component({
  selector: 'app-forgot-password-page',
  imports: [ReactiveFormsModule, RouterLink, AuthLayoutComponent, IconComponent, FormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-auth-layout title="Recuperar contraseña" subtitle="Te enviaremos una contraseña temporal a tu correo registrado.">
      @if (sent()) {
        <div class="space-y-5 animate-fade-up">
          <div class="flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
            <app-icon name="circle-check" [size]="20" class="mt-0.5 shrink-0" />
            <div>
              <p class="font-semibold">¡Listo! Revisa tu bandeja de entrada.</p>
              <p class="mt-1 opacity-90">La contraseña temporal vence en 60 minutos. Al ingresar te pediremos definir una nueva. Tu contraseña actual sigue funcionando mientras tanto.</p>
            </div>
          </div>
          <a routerLink="/login" class="btn btn-dark h-11 w-full">Ir a iniciar sesión</a>
        </div>
      } @else {
        <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-5" novalidate>
          <div>
            <label class="label" for="email">Correo electrónico</label>
            <div class="relative">
              <app-icon name="mail" [size]="17" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400" />
              <input id="email" type="email" class="input pl-10" formControlName="email" autocomplete="email" placeholder="tu@correo.com" />
            </div>
            <app-form-error [control]="form.controls.email" />
          </div>
          @if (error()) {
            <div class="flex items-center gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-700 animate-fade-in dark:bg-red-500/10 dark:text-red-300" role="alert">
              <app-icon name="circle-exclamation-point" [size]="17" /> {{ error() }}
            </div>
          }
          <button type="submit" class="btn btn-dark h-11 w-full" [disabled]="loading()">
            @if (loading()) { <app-icon name="loader" [size]="17" class="animate-spin" /> Enviando… } @else { Enviar contraseña temporal }
          </button>
          <a routerLink="/login" class="block text-center text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white">← Volver al inicio de sesión</a>
        </form>
      }
    </app-auth-layout>
  `,
})
export class ForgotPasswordPage {
  private readonly auth = inject(AuthService);
  protected readonly form = inject(NonNullableFormBuilder).group({
    email: ['', [Validators.required, strictEmail, Validators.maxLength(150)]],
  });
  protected readonly loading = signal(false);
  protected readonly sent = signal(false);
  protected readonly error = signal<string | null>(null);

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.loading.set(true);
    this.error.set(null);
    this.auth.forgotPassword(this.form.getRawValue().email).subscribe({
      next: () => {
        this.loading.set(false);
        this.sent.set(true);
      },
      error: (e: unknown) => {
        this.loading.set(false);
        const status = e instanceof HttpErrorResponse ? e.status : 0;
        this.error.set(
          status === 429
            ? 'Demasiadas solicitudes. Intenta de nuevo en unos minutos.'
            : ((e as HttpErrorResponse).error?.message ?? 'No se pudo procesar la solicitud.'),
        );
      },
    });
  }
}
