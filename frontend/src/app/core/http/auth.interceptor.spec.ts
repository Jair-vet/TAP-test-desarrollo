import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AuthStore } from '../auth/auth.store';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  let http: HttpClient;
  let backend: HttpTestingController;
  let store: AuthStore;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpClient);
    backend = TestBed.inject(HttpTestingController);
    store = TestBed.inject(AuthStore);
    store.setSession({
      status: 'authenticated',
      access_token: 'jwt-1',
      token_type: 'Bearer',
      expires_in: 600,
      user: {
        id: '1', code: 'USR-000001', email: 'a@tap.test', name: 'A', photo_url: null, created_at: '',
        must_change_password: false, two_factor_enabled: false, permissions: {},
      },
    });
  });

  afterEach(() => backend.verify());

  it('agrega el Bearer solo a la API propia', () => {
    http.get('/api/v1/products').subscribe();
    http.get('https://otro-dominio.com/datos').subscribe();

    expect(backend.expectOne('/api/v1/products').request.headers.get('Authorization')).toBe('Bearer jwt-1');
    expect(backend.expectOne('https://otro-dominio.com/datos').request.headers.has('Authorization')).toBeFalse();
  });

  it('ante 401 por token vencido renueva una vez y reintenta', () => {
    let result: unknown;
    http.get('/api/v1/products').subscribe((r) => (result = r));

    backend.expectOne('/api/v1/products').flush({ code: 'TOKEN_EXPIRED' }, { status: 401, statusText: 'Unauthorized' });

    const refresh = backend.expectOne('/api/v1/auth/refresh');
    expect(refresh.request.headers.get('X-Requested-With')).toBe('XMLHttpRequest');
    refresh.flush({ data: { status: 'authenticated', access_token: 'jwt-2', token_type: 'Bearer', expires_in: 600, user: store.user() } });

    const retry = backend.expectOne('/api/v1/products');
    expect(retry.request.headers.get('Authorization')).toBe('Bearer jwt-2');
    retry.flush({ data: [] });
    expect(result).toEqual({ data: [] });
  });
});
