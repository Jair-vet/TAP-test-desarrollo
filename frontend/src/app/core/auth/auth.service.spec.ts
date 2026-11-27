import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { SessionPayload } from '../models/api.models';
import { AuthService } from './auth.service';
import { AuthStore } from './auth.store';

const session = (token = 'jwt-1'): SessionPayload => ({
  status: 'authenticated',
  access_token: token,
  token_type: 'Bearer',
  expires_in: 600,
  user: {
    id: '1', code: 'USR-000001', email: 'ana@tap.test', name: 'Ana López', photo_url: null, created_at: '',
    must_change_password: false, two_factor_enabled: false, permissions: { products: ['view'] },
  },
});

/** Casos de uso de sesión del cliente: login, 2FA, renovación (refresh) y cierre de sesión. */
describe('AuthService', () => {
  let service: AuthService;
  let store: AuthStore;
  let backend: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(AuthService);
    store = TestBed.inject(AuthStore);
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => backend.verify());

  it('login exitoso guarda la sesión en memoria', () => {
    service.login('ana@tap.test', 'Secreta#2026').subscribe();

    const req = backend.expectOne('/api/v1/auth/login');
    expect(req.request.body).toEqual({ email: 'ana@tap.test', password: 'Secreta#2026' });
    req.flush({ data: session() });

    expect(store.token()).toBe('jwt-1');
    expect(store.isAuthenticated()).toBeTrue();
  });

  it('si el usuario tiene 2FA, el login NO abre sesión hasta validar el código', () => {
    let status = '';
    service.login('ana@tap.test', 'Secreta#2026').subscribe((r) => (status = r.status));
    backend.expectOne('/api/v1/auth/login').flush({ data: { status: 'two_factor_required', mfa_token: 'mfa', expires_in: 300 } });

    expect(status).toBe('two_factor_required');
    expect(store.isAuthenticated()).toBeFalse();
  });

  it('2FA envía el código de respaldo en lugar del TOTP cuando se usa', () => {
    service.verifyTwoFactor('mfa', null, 'ABCDE-12345').subscribe();

    const req = backend.expectOne('/api/v1/auth/two-factor/challenge');
    expect(req.request.body).toEqual({ mfa_token: 'mfa', recovery_code: 'ABCDE-12345' });
    req.flush({ data: session('jwt-2fa') });
    expect(store.token()).toBe('jwt-2fa');
  });

  it('refresh manda la cabecera anti-CSRF y comparte UNA sola petición entre llamadas simultáneas', () => {
    const tokens: string[] = [];
    service.refresh().subscribe((s) => tokens.push(s.access_token));
    service.refresh().subscribe((s) => tokens.push(s.access_token));

    const req = backend.expectOne('/api/v1/auth/refresh'); // falla si hubiera dos
    expect(req.request.headers.get('X-Requested-With')).toBe('XMLHttpRequest');
    req.flush({ data: session('jwt-nuevo') });

    expect(tokens).toEqual(['jwt-nuevo', 'jwt-nuevo']);
    expect(store.token()).toBe('jwt-nuevo');
  });

  it('si el refresh falla, limpia la sesión y restoreSession responde false', () => {
    store.setSession(session());
    let restored: boolean | undefined;
    service.restoreSession().subscribe((ok) => (restored = ok));

    backend.expectOne('/api/v1/auth/refresh').flush({ message: 'La sesión expiró' }, { status: 401, statusText: 'Unauthorized' });

    expect(restored).toBeFalse();
    expect(store.isAuthenticated()).toBeFalse();
  });

  it('logout limpia la sesión y regresa al login aunque el servidor no responda', () => {
    const navigate = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);
    store.setSession(session());

    service.logout();
    backend.expectOne('/api/v1/auth/logout').error(new ProgressEvent('error'));

    expect(store.isAuthenticated()).toBeFalse();
    expect(navigate).toHaveBeenCalledWith(['/login']);
  });
});
