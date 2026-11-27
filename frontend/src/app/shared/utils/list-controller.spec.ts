import { TestBed, fakeAsync, tick } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Observable, of, throwError } from 'rxjs';
import { ListParams, Paginated } from '../../core/models/api.models';
import { createListController } from './list-controller';

interface Row {
  id: string;
}

const pageOf = (page: number, lastPage: number, rows: Row[] = [{ id: 'a' }]): Paginated<Row> => ({
  data: rows,
  meta: { page, per_page: 10, total: rows.length, last_page: lastPage },
});

/** Controlador de todas las tablas: búsqueda con debounce, orden, filtros, paginación y errores. */
describe('createListController', () => {
  let calls: ListParams[];

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    calls = [];
  });

  const create = (fetch: (p: ListParams) => Observable<Paginated<Row>> = () => of(pageOf(1, 1))) =>
    TestBed.runInInjectionContext(() =>
      createListController<Row>(
        (p) => {
          calls.push(p);
          return fetch(p);
        },
        { syncUrl: false },
      ),
    );

  /** Propaga los cambios de signals y deja pasar el debounce de 300 ms. */
  const settle = (ms = 300) => {
    TestBed.flushEffects();
    tick(ms);
  };

  it('carga la primera página ordenada por fecha descendente', fakeAsync(() => {
    const list = create();
    settle();

    expect(calls.length).toBe(1);
    expect(calls[0]).toEqual(jasmine.objectContaining({ page: 1, per_page: 10, sort: 'created_at', direction: 'desc' }));
    expect(list.items()).toEqual([{ id: 'a' }]);
    expect(list.loading()).toBeFalse();
  }));

  it('al escribir rápido solo hace UNA petición con el texto final (debounce)', fakeAsync(() => {
    const list = create();
    settle();

    list.setSearch('c');
    settle(100);
    list.setSearch('ca');
    settle(100);
    list.setSearch('  casco ');
    settle();

    expect(calls.length).toBe(2);
    expect(calls[1].search).toBe('casco');
    expect(calls[1].page).toBe(1);
  }));

  it('ordenar por otra columna empieza ascendente; repetirla invierte el orden', fakeAsync(() => {
    const list = create();
    list.page.set(3);

    list.toggleSort('price');
    expect(list.sort()).toBe('price');
    expect(list.direction()).toBe('asc');
    expect(list.page()).toBe(1);

    list.toggleSort('price');
    expect(list.direction()).toBe('desc');
    settle();
  }));

  it('un filtro vacío se quita de los parámetros', fakeAsync(() => {
    const list = create();

    list.setFilter('event', 'updated');
    expect(list.params()['event']).toBe('updated');

    list.setFilter('event', null);
    expect('event' in list.params()).toBeFalse();
    settle();
  }));

  it('si la API falla marca el error y deja de cargar', fakeAsync(() => {
    const list = create(() => throwError(() => new Error('sin conexión')));
    settle();

    expect(list.failed()).toBeTrue();
    expect(list.loading()).toBeFalse();
  }));

  it('si se borró lo último de la última página, retrocede a la anterior', fakeAsync(() => {
    const list = create((p) => of(p.page === 3 ? pageOf(3, 2, []) : pageOf(p.page ?? 1, 2)));
    list.page.set(3);
    settle();
    settle();

    expect(list.page()).toBe(2);
    expect(list.items()).toEqual([{ id: 'a' }]);
  }));
});
