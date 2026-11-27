import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { ApiErrorBody, SessionPayload } from '../../core/models/api.models';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { FormErrorComponent } from '../../shared/ui/form-error.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { AuthLayoutComponent } from './auth-layout.component';
import { strictEmail, totpCode } from '../../shared/utils/validators';

/**
 * Login en dos pasos:
 *  1) correo + contraseña
 *  2) si el usuario tiene 2FA: código de 6 dígitos (o código de respaldo)
 */
@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink, AuthLayoutComponent, IconComponent, FormErrorComponent, AppDatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './login.page.html',
})
export class LoginPage {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Query params (withComponentInputBinding) */
  readonly returnUrl = input<string>();
  readonly expired = input<string>();

  protected readonly step = signal<'credentials' | 'two-factor'>('credentials');
  protected readonly loading = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly lockedUntil = signal<string | null>(null);
  protected readonly showPassword = signal(false);
  protected readonly useRecovery = signal(false);
  private mfaToken = '';

  protected readonly form = this.fb.group({
    email: ['', [Validators.required, strictEmail, Validators.maxLength(150)]],
    password: ['', [Validators.required, Validators.maxLength(200)]],
  });

  protected readonly codeForm = this.fb.group({
    code: ['', [Validators.required, totpCode]],
    recovery: ['', [Validators.pattern(/^[A-Za-z0-9]{5}-?[A-Za-z0-9]{5}$/)]],
  });

  submitCredentials(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.begin();
    const { email, password } = this.form.getRawValue();

    this.auth.login(email, password).subscribe({
      next: (res) => {
        this.loading.set(false);
        if (res.status === 'two_factor_required') {
          this.mfaToken = res.mfa_token;
          this.step.set('two-factor');
        } else {
          this.redirect(res);
        }
      },
      error: (e: unknown) => this.fail(e),
    });
  }

  submitCode(): void {
    const { code, recovery } = this.codeForm.getRawValue();
    const usingRecovery = this.useRecovery();
    if ((!usingRecovery && !/^\d{6}$/.test(code)) || (usingRecovery && !recovery)) {
      this.codeForm.markAllAsTouched();
      return;
    }
    this.begin();
    this.auth.verifyTwoFactor(this.mfaToken, usingRecovery ? null : code, usingRecovery ? recovery : null).subscribe({
      next: (session) => this.redirect(session),
      error: (e: unknown) => {
        this.fail(e);
        this.codeForm.controls.code.reset('');
      },
    });
  }

  /** Autoenvío al capturar el sexto dígito */
  onCodeInput(): void {
    if (/^\d{6}$/.test(this.codeForm.controls.code.value)) this.submitCode();
  }

  backToCredentials(): void {
    this.step.set('credentials');
    this.error.set(null);
    this.codeForm.reset();
  }

  private begin(): void {
    this.loading.set(true);
    this.error.set(null);
    this.lockedUntil.set(null);
  }

  private fail(e: unknown): void {
    this.loading.set(false);
    const body = e instanceof HttpErrorResponse ? (e.error as ApiErrorBody | null) : null;
    if (body?.code === 'ACCOUNT_LOCKED') this.lockedUntil.set(String(body['locked_until']));
    if (body?.code === 'TOKEN_EXPIRED' || body?.code === 'TOKEN_INVALID') this.backToCredentials();
    this.error.set(
      e instanceof HttpErrorResponse && e.status === 429
        ? 'Demasiados intentos. Espera un minuto.'
        : (body?.message ?? 'No se pudo iniciar sesión.'),
    );
  }

  private redirect(session: SessionPayload): void {
    const target = session.user.must_change_password ? '/cuenta/cambiar-contrasena' : this.returnUrl() || '/';
    void this.router.navigateByUrl(target);
  }
}
