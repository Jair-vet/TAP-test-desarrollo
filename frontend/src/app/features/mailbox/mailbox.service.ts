import { HttpClient, HttpContext } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../../core/http/api.config';
import { SKIP_AUTH_REFRESH, SKIP_ERROR_TOAST } from '../../core/http/http-context';

/** Ruta pública de Mailpit detrás de Nginx. */
export const MAILBOX_URL = '/mail/';

/**
 * Acceso a la bandeja de correos de prueba (Mailpit).
 *
 * Nginx protege /mail/ con `auth_request`: solo deja pasar si existe la cookie
 * firmada `tap_mail` (HttpOnly, Path=/mail/). Esta cookie se obtiene:
 *  - con usuario/contraseña de la bandeja (pantalla /correos), o
 *  - con la sesión actual si el usuario puede crear usuarios (menú "Correos").
 * Reemplaza el diálogo nativo de autenticación básica del navegador.
 */
@Injectable({ providedIn: 'root' })
export class MailboxService {
  private readonly http = inject(HttpClient);
  private readonly base = `${API_URL}/mailbox/session`;

  login(user: string, password: string): Observable<void> {
    return this.http.post<void>(this.base, { user, password }, {
      context: new HttpContext().set(SKIP_ERROR_TOAST, true).set(SKIP_AUTH_REFRESH, true),
    });
  }

  /**
   * Emite la cookie de la bandeja con la sesión actual (requiere permiso users:create).
   * Los errores los muestra el interceptor global (403 "Acceso denegado", sin conexión…).
   */
  sessionPass(): Observable<void> {
    return this.http.post<void>(`${this.base}/me`, null);
  }

  /**
   * Menú lateral: abre la bandeja en otra pestaña con la sesión actual. La pestaña se
   * abre ANTES de la petición (gesto del usuario) para que el navegador no la bloquee.
   */
  openWithSession(): void {
    const tab = window.open('about:blank', '_blank');
    this.sessionPass().subscribe({
      next: () => {
        if (tab) tab.location.href = MAILBOX_URL;
        else window.location.href = MAILBOX_URL;
      },
      error: () => tab?.close(),
    });
  }

  /** Solo se permite volver a rutas dentro de /mail/ (evita redirecciones abiertas). */
  static safeNext(next: string | null | undefined): string {
    return next && next.startsWith(MAILBOX_URL) && !next.includes('//', 1) ? next : MAILBOX_URL;
  }
}
