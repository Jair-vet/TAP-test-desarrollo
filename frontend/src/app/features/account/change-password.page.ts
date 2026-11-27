import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { AuthStore } from '../../core/auth/auth.store';
import { ToastService } from '../../core/ui/toast.service';
import { FormErrorComponent } from '../../shared/ui/form-error.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { PageHeaderComponent } from '../../shared/ui/states.component';
import { applyServerErrors } from '../../shared/utils/form-errors';

const RULES = [
  { key: 'length', label: 'Mínimo 10 caracteres', test: (v: string) => v.length >= 10 },
  { key: 'case', label: 'Mayúsculas y minúsculas', test: (v: string) => /[a-z]/.test(v) && /[A-Z]/.test(v) },
  { key: 'number', label: 'Al menos un número', test: (v: string) => /\d/.test(v) },
  { key: 'symbol', label: 'Al menos un símbolo', test: (v: string) => /[^A-Za-z0-9]/.test(v) },
];

/** Política idéntica a la del backend (ChangePasswordRequest). */
function passwordPolicy(control: AbstractControl): ValidationErrors | null {
  const value = String(control.value ?? '');
  return RULES.every((r) => r.test(value)) ? null : { server: 'La contraseña no cumple la política.' };
}

/**
 * Cambio de contraseña. También es la pantalla obligatoria tras entrar con
 * una contraseña temporal o en el primer ingreso.
 */
@Component({
  selector: 'app-change-password-page',
  imports: [ReactiveFormsModule, IconComponent, PageHeaderComponent, FormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header eyebrow="Mi cuenta" title="Cambiar contraseña"
      [subtitle]="forced() ? 'Por seguridad, define una contraseña nueva antes de continuar.' : 'Al cambiarla se cerrarán tus demás sesiones.'" />

    <section class="card max-w-xl p-6 animate-fade-up">
      @if (forced()) {
        <div class="mb-5 flex items-start gap-2 rounded-xl border border-brand-300 bg-brand-50 p-3 text-sm text-brand-700 dark:border-brand-400/30 dark:bg-brand-400/10 dark:text-brand-300">
          <app-icon name="key-round" [size]="17" class="mt-0.5" /> Ingresaste con una contraseña asignada por el sistema. Escríbela como "contraseña actual".
        </div>
      }
      <form [formGroup]="form" (ngSubmit)="save()" class="space-y-5" novalidate>
        <div>
          <label class="label" for="current">Contraseña actual</label>
          <input id="current" type="password" class="input" formControlName="current" autocomplete="current-password" />
          <app-form-error [control]="form.controls.current" />
        </div>
        <div>
          <label class="label" for="password">Nueva contraseña</label>
          <input id="password" [type]="show() ? 'text' : 'password'" class="input" formControlName="password" autocomplete="new-password" />
          <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10">
            <div class="h-full rounded-full transition-all duration-500 ease-out" [style.width.%]="strength() * 25"
              [class]="strength() <= 1 ? 'bg-red-500' : strength() === 2 ? 'bg-amber-500' : strength() === 3 ? 'bg-brand-400' : 'bg-emerald-500'"></div>
          </div>
          <ul class="mt-3 grid gap-1.5 text-xs sm:grid-cols-2">
            @for (r of checks(); track r.key) {
              <li class="flex items-center gap-1.5 transition-colors duration-300" [class]="r.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500'">
                <app-icon [name]="r.ok ? 'circle-check' : 'circle-x'" [size]="13" /> {{ r.label }}
              </li>
            }
          </ul>
        </div>
        <div>
          <label class="label" for="confirm">Confirmar nueva contraseña</label>
          <input id="confirm" [type]="show() ? 'text' : 'password'" class="input" formControlName="confirmation" autocomplete="new-password" />
          @if (form.touched && form.errors?.['mismatch']) { <p class="error-text">Las contraseñas no coinciden.</p> }
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
          <input type="checkbox" class="accent-ink-950" (change)="show.set(!show())" /> Mostrar contraseñas
        </label>

        @if (error()) {
          <div class="flex items-center gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-700 animate-fade-in dark:bg-red-500/10 dark:text-red-300" role="alert">
            <app-icon name="circle-exclamation-point" [size]="17" /> {{ error() }}
          </div>
        }
        <button type="submit" class="btn btn-primary w-full sm:w-auto" [disabled]="saving()">
          @if (saving()) { <app-icon name="loader" [size]="16" class="animate-spin" /> Guardando… } @else { Guardar contraseña }
        </button>
      </form>
    </section>
  `,
})
export class ChangePasswordPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  protected readonly forced = inject(AuthStore).mustChangePassword;

  protected readonly show = signal(false);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = inject(NonNullableFormBuilder).group(
    {
      current: ['', [Validators.required]],
      password: ['', [Validators.required, Validators.maxLength(200), passwordPolicy]],
      confirmation: ['', [Validators.required]],
    },
    { validators: (g: AbstractControl) => (g.get('password')?.value === g.get('confirmation')?.value ? null : { mismatch: true }) },
  );

  private readonly password = toSignal(this.form.controls.password.valueChanges, { initialValue: '' });
  protected readonly checks = computed(() => RULES.map((r) => ({ key: r.key, label: r.label, ok: r.test(this.password()) })));
  protected readonly strength = computed(() => this.checks().filter((c) => c.ok).length);

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    this.error.set(null);
    const { current, password, confirmation } = this.form.getRawValue();
    this.auth.changePassword(current, password, confirmation).subscribe({
      next: () => {
        this.toast.success('Contraseña actualizada', 'Se cerraron tus demás sesiones.');
        void this.router.navigateByUrl('/');
      },
      error: (e: unknown) => {
        this.saving.set(false);
        this.error.set(applyServerErrors(this.form, e, { current_password: 'current' }));
      },
    });
  }
}
