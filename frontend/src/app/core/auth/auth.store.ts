import { Injectable, computed, signal } from '@angular/core';
import { ActionKey, AuthUser, SectionKey, SessionPayload } from '../models/api.models';

/**
 * Estado de la sesión con signals.
 *
 * Seguridad: el access token vive SOLO en memoria (nunca en localStorage ni
 * sessionStorage), así un XSS no puede robarlo de forma persistente. Al
 * recargar la página se recupera con /auth/refresh usando la cookie HttpOnly.
 */
@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly _token = signal<string | null>(null);
  private readonly _expiresAt = signal<number>(0);
  private readonly _user = signal<AuthUser | null>(null);

  readonly user = this._user.asReadonly();
  readonly token = this._token.asReadonly();
  readonly expiresAt = this._expiresAt.asReadonly();
  readonly isAuthenticated = computed(() => this._token() !== null && this._user() !== null);
  readonly mustChangePassword = computed(() => this._user()?.must_change_password ?? false);
  readonly initials = computed(() => {
    const parts = (this._user()?.name ?? '').split(/\s+/).filter(Boolean);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || 'U';
  });

  setSession(session: SessionPayload): void {
    this._token.set(session.access_token);
    this._expiresAt.set(Date.now() + session.expires_in * 1000);
    this._user.set(session.user);
  }

  patchUser(patch: Partial<AuthUser>): void {
    const current = this._user();
    if (current) this._user.set({ ...current, ...patch });
  }

  clear(): void {
    this._token.set(null);
    this._expiresAt.set(0);
    this._user.set(null);
  }

  /** ¿Tiene la acción en la sección? (solo UX: el servidor vuelve a validar). */
  can(section: SectionKey, action: ActionKey = 'view'): boolean {
    return this._user()?.permissions[section]?.includes(action) ?? false;
  }
}
