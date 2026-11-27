import { HttpErrorResponse, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, switchMap, throwError } from 'rxjs';
import { AuthService } from '../auth/auth.service';
import { AuthStore } from '../auth/auth.store';
import { API_URL } from './api.config';
import { SKIP_AUTH_REFRESH } from './http-context';

const RETRYABLE = new Set(['TOKEN_EXPIRED', 'TOKEN_REVOKED', 'TOKEN_INVALID', 'UNAUTHENTICATED']);

const withToken = (req: HttpRequest<unknown>, token: string | null) =>
  token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;

/**
 * 1) Agrega `Authorization: Bearer <jwt>` SOLO a peticiones de nuestra API
 *    (nunca se filtra el token a otro dominio).
 * 2) Ante un 401 por token vencido/revocado: renueva UNA vez con la cookie y
 *    reintenta. Si el refresh falla → login.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  if (!req.url.startsWith(API_URL)) return next(req);

  const store = inject(AuthStore);
  const auth = inject(AuthService);
  const router = inject(Router);

  return next(withToken(req, store.token())).pipe(
    catchError((error: unknown) => {
      const code = error instanceof HttpErrorResponse ? (error.error?.code as string | undefined) : undefined;
      const canRetry =
        error instanceof HttpErrorResponse &&
        error.status === 401 &&
        !req.context.get(SKIP_AUTH_REFRESH) &&
        (!code || RETRYABLE.has(code));

      if (!canRetry) return throwError(() => error);

      return auth.refresh().pipe(
        switchMap((session) => next(withToken(req, session.access_token))),
        catchError((refreshError: unknown) => {
          store.clear();
          void router.navigate(['/login'], { queryParams: { returnUrl: router.url, expired: 1 } });
          return throwError(() => refreshError);
        }),
      );
    }),
  );
};
