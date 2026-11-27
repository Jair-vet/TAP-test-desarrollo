import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { DomSanitizer, SafeUrl } from '@angular/platform-browser';
import { RouterLink } from '@angular/router';
import { AuthStore } from '../../core/auth/auth.store';
import { ToastService } from '../../core/ui/toast.service';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { IconComponent } from '../../shared/ui/icon.component';
import { PageHeaderComponent } from '../../shared/ui/states.component';
import { AccountApi, TwoFactorStatus } from './account.api';
import { totpCode } from '../../shared/utils/validators';
import { FormErrorComponent } from '../../shared/ui/form-error.component';

type Step = 'idle' | 'scan' | 'codes' | 'disable' | 'regenerate';

/**
 * Seguridad de la cuenta: verificación en dos pasos (TOTP) y códigos de respaldo.
 * Flujo de activación: generar QR → escanear → confirmar código → guardar códigos.
 */
@Component({
  selector: 'app-security-page',
  imports: [ReactiveFormsModule, RouterLink, IconComponent, PageHeaderComponent, AppDatePipe, FormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './security.page.html',
})
export class SecurityPage {
  private readonly api = inject(AccountApi);
  private readonly toast = inject(ToastService);
  private readonly sanitizer = inject(DomSanitizer);
  private readonly fb = inject(NonNullableFormBuilder);
  protected readonly store = inject(AuthStore);

  protected readonly status = signal<TwoFactorStatus | null>(null);
  protected readonly step = signal<Step>('idle');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly qr = signal<SafeUrl | null>(null);
  protected readonly secret = signal('');
  protected readonly codes = signal<string[]>([]);

  protected readonly codeForm = this.fb.group({ code: ['', [Validators.required, totpCode]] });
  protected readonly disableForm = this.fb.group({
    password: ['', [Validators.required, Validators.maxLength(200)]],
    code: ['', [Validators.required, totpCode]],
  });
  protected readonly passwordForm = this.fb.group({ password: ['', [Validators.required, Validators.maxLength(200)]] });

  constructor() {
    this.loadStatus();
  }

  startSetup(): void {
    this.run(this.api.setup(), (setup) => {
      // SVG generado por NUESTRO backend (bacon-qr-code): se marca como confiable
      // para pintarlo como imagen data: (la CSP permite img-src data:).
      const dataUrl = 'data:image/svg+xml;base64,' + btoa(setup.qr_svg);
      this.qr.set(this.sanitizer.bypassSecurityTrustUrl(dataUrl));
      this.secret.set(setup.secret.replace(/(.{4})/g, '$1 ').trim());
      this.codeForm.reset();
      this.step.set('scan');
    });
  }

  confirm(): void {
    if (this.codeForm.invalid) return this.codeForm.markAllAsTouched();
    this.run(this.api.confirm(this.codeForm.getRawValue().code), (session) => {
      this.codes.set(session.recovery_codes ?? []);
      this.step.set('codes');
      this.toast.success('2FA activado', 'Tu cuenta ahora pide un código al iniciar sesión.');
      this.loadStatus();
    });
  }

  disable(): void {
    if (this.disableForm.invalid) return this.disableForm.markAllAsTouched();
    const { password, code } = this.disableForm.getRawValue();
    this.run(this.api.disable(password, code), () => {
      this.toast.warning('2FA desactivado', 'Te recomendamos volver a activarlo.');
      this.disableForm.reset();
      this.step.set('idle');
      this.loadStatus();
    });
  }

  regenerate(): void {
    if (this.passwordForm.invalid) return this.passwordForm.markAllAsTouched();
    this.run(this.api.regenerateRecoveryCodes(this.passwordForm.getRawValue().password), (codes) => {
      this.codes.set(codes);
      this.passwordForm.reset();
      this.step.set('codes');
      this.loadStatus();
    });
  }

  copy(text: string): void {
    void navigator.clipboard?.writeText(text).then(() => this.toast.info('Copiado al portapapeles'));
  }

  downloadCodes(): void {
    const content = `Códigos de respaldo — TAP Gestión\nCuenta: ${this.store.user()?.email}\n\n${this.codes().join('\n')}\n\nCada código funciona una sola vez.\n`;
    const url = URL.createObjectURL(new Blob([content], { type: 'text/plain' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'codigos-respaldo-tap.txt';
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  cancel(): void {
    this.step.set('idle');
    this.error.set(null);
  }

  private loadStatus(): void {
    this.api.status().subscribe((s) => this.status.set(s));
  }

  private run<T>(request: import('rxjs').Observable<T>, onSuccess: (value: T) => void): void {
    this.busy.set(true);
    this.error.set(null);
    request.subscribe({
      next: (value) => {
        this.busy.set(false);
        onSuccess(value);
      },
      error: (e: unknown) => {
        this.busy.set(false);
        this.error.set(e instanceof HttpErrorResponse ? (e.error?.message ?? 'Ocurrió un error.') : 'Ocurrió un error.');
      },
    });
  }
}
