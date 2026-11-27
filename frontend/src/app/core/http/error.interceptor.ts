import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { ToastService } from '../ui/toast.service';
import { SKIP_ERROR_TOAST } from './http-context';

/**
 * Manejo global de errores HTTP → toasts en español.
 * Los 422 (validación) y 409 (conflicto) los resuelve cada formulario, salvo
 * que no lo marque con SKIP_ERROR_TOAST.
 */
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const toast = inject(ToastService);
  const router = inject(Router);

  return next(req).pipe(
    catchError((error: unknown) => {
      if (!(error instanceof HttpErrorResponse) || req.context.get(SKIP_ERROR_TOAST)) {
        return throwError(() => error);
      }

      const body = error.error as { message?: string; code?: string; request_id?: string } | null;

      switch (true) {
        case error.status === 0:
          toast.error('Sin conexión con el servidor', 'Revisa tu red e intenta de nuevo.');
          break;
        case error.status === 401:
          break; // lo maneja authInterceptor
        case error.status === 403 && body?.code === 'PASSWORD_CHANGE_REQUIRED':
          void router.navigate(['/cuenta/cambiar-contrasena']);
          break;
        case error.status === 403:
          toast.warning('Acceso denegado', body?.message ?? 'No tienes permiso para esta acción.');
          break;
        case error.status === 404:
          toast.warning('No encontrado', body?.message ?? 'El registro ya no existe.');
          break;
        case error.status === 409 || error.status === 422:
          toast.warning('Revisa la información', body?.message ?? 'Hay datos inválidos.');
          break;
        case error.status === 429:
          toast.warning('Demasiadas solicitudes', 'Espera un momento antes de reintentar.');
          break;
        default:
          toast.error('Ocurrió un error', body?.request_id ? `Referencia: ${body.request_id}` : 'Intenta más tarde.');
      }

      return throwError(() => error);
    }),
  );
};
