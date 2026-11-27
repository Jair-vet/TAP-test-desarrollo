import { TestBed } from '@angular/core/testing';
import { SessionPayload } from '../models/api.models';
import { AuthStore } from './auth.store';

const session = (overrides: Partial<SessionPayload['user']> = {}): SessionPayload => ({
  status: 'authenticated',
  access_token: 'jwt',
  token_type: 'Bearer',
  expires_in: 600,
  user: {
    id: '1',
    code: 'USR-000001',
    email: 'ana@tap.test',
    name: 'Ana López',
    photo_url: null,
    created_at: '2026-11-25T18:00:00+00:00',
    must_change_password: false,
    two_factor_enabled: false,
    permissions: { products: ['view', 'update'], users: ['view'] },
    ...overrides,
  },
});

describe('AuthStore', () => {
  let store: AuthStore;

  beforeEach(() => {
    store = TestBed.inject(AuthStore);
  });

  it('inicia sin sesión', () => {
    expect(store.isAuthenticated()).toBeFalse();
    expect(store.can('products')).toBeFalse();
  });

  it('evalúa permisos por sección y acción', () => {
    store.setSession(session());
    expect(store.isAuthenticated()).toBeTrue();
    expect(store.can('products', 'update')).toBeTrue();
    expect(store.can('products', 'delete')).toBeFalse();
    expect(store.can('profiles')).toBeFalse();
    expect(store.initials()).toBe('AL');
  });

  it('expone si debe cambiar la contraseña', () => {
    store.setSession(session({ must_change_password: true }));
    expect(store.mustChangePassword()).toBeTrue();
  });

  it('limpia la sesión al cerrar', () => {
    store.setSession(session());
    store.clear();
    expect(store.token()).toBeNull();
    expect(store.isAuthenticated()).toBeFalse();
  });
});
