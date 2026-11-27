import { HttpClient, HttpContext, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ToastService } from '../ui/toast.service';
import { errorInterceptor } from './error.interceptor';
import { SKIP_ERROR_TOAST } from './http-context';

describe('errorInterceptor (errores globales → mensajes en español)', () => {
  let http: HttpClient;
  let backend: HttpTestingController;
  let toast: ToastService;

  const fail = (status: number, body: object | null = null) => {
    http.get('/api/v1/x').subscribe({ error: () => void 0 });
    backend.expectOne('/api/v1/x').flush(body, { status, statusText: 'x' });
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(withInterceptors([errorInterceptor])), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpClient);
    backend = TestBed.inject(HttpTestingController);
    toast = TestBed.inject(ToastService);
  });

  afterEach(() => backend.verify());

  it('403 sin permiso muestra aviso de acceso denegado', () => {
    fail(403, { code: 'FORBIDDEN', message: 'No tienes permiso para realizar esta acción.' });
    expect(toast.toasts()[0]).toEqual(jasmine.objectContaining({ kind: 'warning', title: 'Acceso denegado' }));
  });

  it('403 por contraseña pendiente redirige a cambiarla (sin toast)', () => {
    const navigate = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);
    fail(403, { code: 'PASSWORD_CHANGE_REQUIRED', message: '…' });
    expect(navigate).toHaveBeenCalledWith(['/cuenta/cambiar-contrasena']);
    expect(toast.toasts().length).toBe(0);
  });

  it('500 muestra la referencia (request_id) para buscar en logs', () => {
    fail(500, { code: 'INTERNAL_ERROR', message: 'x', request_id: 'abc-123' });
    expect(toast.toasts()[0].message).toContain('abc-123');
  });

  it('401 lo resuelve el interceptor de sesión: no muestra toast', () => {
    fail(401, { code: 'TOKEN_EXPIRED' });
    expect(toast.toasts().length).toBe(0);
  });

  it('respeta SKIP_ERROR_TOAST (formularios que muestran el error en línea)', () => {
    http.get('/api/v1/y', { context: new HttpContext().set(SKIP_ERROR_TOAST, true) }).subscribe({ error: () => void 0 });
    backend.expectOne('/api/v1/y').flush({ code: 'VALIDATION_ERROR' }, { status: 422, statusText: 'x' });
    expect(toast.toasts().length).toBe(0);
  });
});
