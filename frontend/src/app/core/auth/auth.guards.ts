import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { ActionKey, SectionKey } from '../models/api.models';
import { AuthStore } from './auth.store';

/** Solo con sesión. Si falta cambiar la contraseña, se manda a hacerlo. */
export const authGuard: CanActivateFn = (_route, state) => {
  const store = inject(AuthStore);
  const router = inject(Router);

  if (!store.isAuthenticated()) {
    return router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
  }
  if (store.mustChangePassword() && !state.url.startsWith('/cuenta/cambiar-contrasena')) {
    return router.createUrlTree(['/cuenta/cambiar-contrasena']);
  }
  return true;
};

/** Login/recuperación: si ya hay sesión, al inicio. */
export const guestGuard: CanActivateFn = () => {
  const store = inject(AuthStore);
  return store.isAuthenticated() ? inject(Router).createUrlTree(['/']) : true;
};

/**
 * "Los usuarios solo podrán ingresar a las secciones asignadas en sus perfiles."
 * Uso en rutas: canActivate: [permissionGuard('products')]
 */
export const permissionGuard =
  (section: SectionKey, action: ActionKey = 'view'): CanActivateFn =>
  () =>
    inject(AuthStore).can(section, action) ? true : inject(Router).createUrlTree(['/sin-acceso']);
