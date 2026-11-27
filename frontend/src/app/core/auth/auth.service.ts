import { HttpClient, HttpContext, HttpHeaders } from '@angular/common/http';
import { DestroyRef, Injectable, effect, inject } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, finalize, map, of, shareReplay, tap, throwError } from 'rxjs';
import { API_URL } from '../http/api.config';
import { SKIP_AUTH_REFRESH, SKIP_ERROR_TOAST } from '../http/http-context';
import { DataResponse, LoginResponse, SessionPayload } from '../models/api.models';
import { AuthStore } from './auth.store';

/**
 * Casos de uso de autenticación del lado del cliente.
 * Si te piden cambiar un endpoint de sesión, es aquí (y en API_URL).
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly store = inject(AuthStore);
  private readonly router = inject(Router);

  /** Un solo refresh en vuelo aunque fallen 10 peticiones a la vez. */
  private refreshInFlight$: Observable<SessionPayload> | null = null;
  private refreshTimer: ReturnType<typeof setTimeout> | null = null;

  private readonly authContext = () =>
    new HttpContext().set(SKIP_AUTH_REFRESH, true).set(SKIP_ERROR_TOAST, true);

  constructor() {
    // Renovación proactiva: 60 s antes de que expire el access token
    effect(() => {
      const expiresAt = this.store.expiresAt();
      if (this.refreshTimer) clearTimeout(this.refreshTimer);
      if (!expiresAt) return;
      const delay = Math.max(5_000, expiresAt - Date.now() - 60_000);
      this.refreshTimer = setTimeout(() => this.refresh().subscribe({ error: () => void 0 }), delay);
    });
    inject(DestroyRef).onDestroy(() => this.refreshTimer && clearTimeout(this.refreshTimer));
  }

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http
      .post<DataResponse<LoginResponse>>(`${API_URL}/auth/login`, { email, password }, { context: this.authContext() })
      .pipe(
        map((r) => r.data),
        tap((data) => data.status === 'authenticated' && this.store.setSession(data)),
      );
  }

  verifyTwoFactor(mfaToken: string, code: string | null, recoveryCode: string | null): Observable<SessionPayload> {
    const body = recoveryCode ? { mfa_token: mfaToken, recovery_code: recoveryCode } : { mfa_token: mfaToken, code };
    return this.http
      .post<DataResponse<SessionPayload>>(`${API_URL}/auth/two-factor/challenge`, body, { context: this.authContext() })
      .pipe(
        map((r) => r.data),
        tap((s) => this.store.setSession(s)),
      );
  }

  /** Usa la cookie HttpOnly; el header X-Requested-With es parte de la defensa CSRF. */
  refresh(): Observable<SessionPayload> {
    this.refreshInFlight$ ??= this.http
      .post<DataResponse<SessionPayload>>(`${API_URL}/auth/refresh`, null, {
        context: this.authContext(),
        headers: new HttpHeaders({ 'X-Requested-With': 'XMLHttpRequest' }),
      })
      .pipe(
        map((r) => r.data),
        tap((s) => this.store.setSession(s)),
        catchError((e) => {
          this.store.clear();
          return throwError(() => e);
        }),
        finalize(() => (this.refreshInFlight$ = null)),
        shareReplay({ bufferSize: 1, refCount: false }),
      );
    return this.refreshInFlight$;
  }

  /** Al arrancar la app: si hay cookie válida, se restaura la sesión sin pedir login. */
  restoreSession(): Observable<boolean> {
    return this.refresh().pipe(
      map(() => true),
      catchError(() => of(false)),
    );
  }

  logout(redirect = true): void {
    this.http
      .post(`${API_URL}/auth/logout`, null, { context: this.authContext() })
      .pipe(catchError(() => of(null)))
      .subscribe(() => {
        this.store.clear();
        if (redirect) void this.router.navigate(['/login']);
      });
  }

  forgotPassword(email: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${API_URL}/auth/forgot-password`, { email }, { context: this.authContext() });
  }

  changePassword(current: string, password: string, confirmation: string): Observable<SessionPayload> {
    return this.http
      .put<DataResponse<SessionPayload>>(
        `${API_URL}/auth/password`,
        { current_password: current, password, password_confirmation: confirmation },
        { context: new HttpContext().set(SKIP_ERROR_TOAST, true) },
      )
      .pipe(
        map((r) => r.data),
        tap((s) => this.store.setSession(s)),
      );
  }
}
