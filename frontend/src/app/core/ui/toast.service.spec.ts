import { TestBed, fakeAsync, tick } from '@angular/core/testing';
import { ToastService } from './toast.service';

describe('ToastService', () => {
  let toast: ToastService;

  beforeEach(() => (toast = TestBed.inject(ToastService)));

  it('agrega notificaciones y las retira solas', fakeAsync(() => {
    toast.success('Guardado', 'PRD-000001');
    expect(toast.toasts().length).toBe(1);
    tick(4500);
    expect(toast.toasts().length).toBe(0);
  }));

  it('mantiene como máximo 4 visibles (las más viejas salen primero)', fakeAsync(() => {
    ['a', 'b', 'c', 'd', 'e'].forEach((t) => toast.info(t));
    expect(toast.toasts().map((t) => t.title)).toEqual(['b', 'c', 'd', 'e']);
    tick(5000);
  }));

  it('se pueden cerrar manualmente', fakeAsync(() => {
    toast.error('Falló');
    toast.dismiss(toast.toasts()[0].id);
    expect(toast.toasts().length).toBe(0);
    tick(7000);
  }));
});
