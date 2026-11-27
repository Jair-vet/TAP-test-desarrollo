import { HttpClient, HttpContext } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, tap } from 'rxjs';
import { AuthStore } from '../../core/auth/auth.store';
import { API_URL } from '../../core/http/api.config';
import { SKIP_ERROR_TOAST } from '../../core/http/http-context';
import { DataResponse, SessionPayload } from '../../core/models/api.models';

export interface TwoFactorStatus {
  enabled: boolean;
  recovery_codes_remaining: number;
  enabled_at: string | null;
  required: boolean;
}

export interface TwoFactorSetup {
  secret: string;
  otpauth_url: string;
  qr_svg: string;
}

/** Endpoints de "Mi cuenta → Seguridad" (2FA). */
@Injectable({ providedIn: 'root' })
export class AccountApi {
  private readonly http = inject(HttpClient);
  private readonly store = inject(AuthStore);
  private readonly base = `${API_URL}/auth/two-factor`;
  private readonly quiet = () => new HttpContext().set(SKIP_ERROR_TOAST, true);

  status(): Observable<TwoFactorStatus> {
    return this.http.get<DataResponse<TwoFactorStatus>>(this.base).pipe(map((r) => r.data));
  }

  setup(): Observable<TwoFactorSetup> {
    return this.http.post<DataResponse<TwoFactorSetup>>(`${this.base}/setup`, null).pipe(map((r) => r.data));
  }

  /** Activa el 2FA; el backend rota la sesión y devuelve los códigos de respaldo. */
  confirm(code: string): Observable<SessionPayload> {
    return this.http.post<DataResponse<SessionPayload>>(`${this.base}/confirm`, { code }, { context: this.quiet() }).pipe(
      map((r) => r.data),
      tap((s) => this.store.setSession(s)),
    );
  }

  disable(password: string, code: string): Observable<SessionPayload> {
    return this.http
      .delete<DataResponse<SessionPayload>>(this.base, { body: { password, code }, context: this.quiet() })
      .pipe(
        map((r) => r.data),
        tap((s) => this.store.setSession(s)),
      );
  }

  regenerateRecoveryCodes(password: string): Observable<string[]> {
    return this.http
      .post<DataResponse<{ recovery_codes: string[] }>>(`${this.base}/recovery-codes`, { password }, { context: this.quiet() })
      .pipe(map((r) => r.data.recovery_codes));
  }
}
