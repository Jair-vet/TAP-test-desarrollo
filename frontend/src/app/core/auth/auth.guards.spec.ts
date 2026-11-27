import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { Permissions, SessionPayload } from '../models/api.models';
import { authGuard, guestGuard, permissionGuard } from './auth.guards';
import { AuthStore } from './auth.store';

/** "Los usuarios solo podrán ingresar a las secciones asignadas en sus perfiles." */
describe('Guards de autenticación y permisos', () => {
  let store: AuthStore;
  let router: Router;
  const route = {} as ActivatedRouteSnapshot;
  const state = (url: string) => ({ url }) as RouterStateSnapshot;

  const login = (permissions: Permissions, mustChange = false) =>
    store.setSession({
      status: 'authenticated',
      access_token: 'jwt',
      token_type: 'Bearer',
      expires_in: 600,
      user: {
        id: '1', code: 'USR-000001', email: 'a@tap.test', name: 'Ana', photo_url: null, created_at: '',
        must_change_password: mustChange, two_factor_enabled: false, permissions,
      },
    } satisfies SessionPayload);

  const run = <T>(fn: () => T): T => TestBed.runInInjectionContext(fn);
  const urlOf = (result: unknown) => router.serializeUrl(result as UrlTree);

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    store = TestBed.inject(AuthStore);
    router = TestBed.inject(Router);
  });

  it('authGuard: sin sesión manda al login conservando la URL', () => {
    const result = run(() => authGuard(route, state('/productos')));
    expect(urlOf(result)).toBe('/login?returnUrl=%2Fproductos');
  });

  it('authGuard: con contraseña pendiente obliga a cambiarla', () => {
    login({ products: ['view'] }, true);
    expect(urlOf(run(() => authGuard(route, state('/productos'))))).toBe('/cuenta/cambiar-contrasena');
    expect(run(() => authGuard(route, state('/cuenta/cambiar-contrasena')))).toBeTrue();
  });

  it('authGuard: con sesión deja pasar', () => {
    login({ products: ['view'] });
    expect(run(() => authGuard(route, state('/productos')))).toBeTrue();
  });

  it('guestGuard: si ya hay sesión manda al inicio', () => {
    expect(run(() => guestGuard(route, state('/login')))).toBeTrue();
    login({});
    expect(urlOf(run(() => guestGuard(route, state('/login'))))).toBe('/');
  });

  it('permissionGuard: solo entra a secciones de su perfil', () => {
    login({ products: ['view', 'update'] });
    expect(run(() => permissionGuard('products')(route, state('/productos')))).toBeTrue();
    expect(run(() => permissionGuard('products', 'delete')(route, state('/productos')))).not.toBeTrue();
    expect(urlOf(run(() => permissionGuard('profiles')(route, state('/perfiles'))))).toBe('/sin-acceso');
  });
});
