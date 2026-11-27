import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { ApplicationConfig, inject, provideAppInitializer, provideZoneChangeDetection } from '@angular/core';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import {
  provideRouter,
  withComponentInputBinding,
  withInMemoryScrolling,
  withViewTransitions,
} from '@angular/router';
import { provideLucideIcons } from '@lucide/angular';
import { firstValueFrom } from 'rxjs';
import { APP_ICONS } from './app.icons';
import { routes } from './app.routes';
import { AuthService } from './core/auth/auth.service';
import { authInterceptor } from './core/http/auth.interceptor';
import { errorInterceptor } from './core/http/error.interceptor';
import { loadingInterceptor } from './core/http/loading.interceptor';

/**
 * Configuración global de la aplicación (bootstrap standalone):
 * router con transiciones, HttpClient con interceptores, íconos y la
 * renovación silenciosa de la sesión antes de pintar la primera pantalla.
 */
export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(
      routes,
      withComponentInputBinding(),
      withInMemoryScrolling({ scrollPositionRestoration: 'top' }),
      // Transiciones nativas entre páginas (View Transitions API).
      // Se omiten cuando solo cambian query params (búsqueda/paginación).
      withViewTransitions({
        skipInitialTransition: true,
        onViewTransitionCreated: ({ transition, from, to }) => {
          const path = (snap: typeof from) => snap.pathFromRoot.map((s) => s.url.map((u) => u.path).join('/')).join('/');
          if (path(from) === path(to)) {
            transition.ready.catch(() => void 0); // al saltarla, `ready` se rechaza: no es error
            transition.skipTransition();
          }
        },
      }),
    ),
    // Orden: progreso → auth (token/refresh) → errores (toasts)
    provideHttpClient(withFetch(), withInterceptors([loadingInterceptor, authInterceptor, errorInterceptor])),
    provideAnimationsAsync(),
    provideLucideIcons(...APP_ICONS),
    // Antes de pintar: intenta restaurar la sesión con la cookie HttpOnly (refresh silencioso)
    provideAppInitializer(() => firstValueFrom(inject(AuthService).restoreSession())),
  ],
};
