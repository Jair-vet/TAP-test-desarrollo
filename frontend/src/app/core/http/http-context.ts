import { HttpContextToken } from '@angular/common/http';

/** Peticiones de sesión: no intentar refresh automático ante un 401. */
export const SKIP_AUTH_REFRESH = new HttpContextToken<boolean>(() => false);

/** El componente maneja el error (ej. formularios con errores por campo). */
export const SKIP_ERROR_TOAST = new HttpContextToken<boolean>(() => false);

/** No mostrar la barra de progreso global (ej. búsquedas en vivo). */
export const SILENT_LOADING = new HttpContextToken<boolean>(() => false);
