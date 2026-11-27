import { DestroyRef, Signal, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { Observable, Subject, catchError, debounceTime, distinctUntilChanged, map, merge, of, switchMap, tap } from 'rxjs';
import { ListParams, PageMeta, Paginated, SortDirection } from '../../core/models/api.models';

export interface ListControllerOptions {
  sort?: string;
  direction?: SortDirection;
  perPage?: number;
  /** Sincroniza búsqueda/página/orden con la URL (?search=&page=...) para poder compartirla. */
  syncUrl?: boolean;
}

/**
 * Controlador reutilizable de tablas: búsqueda con debounce, orden,
 * paginación, estado de carga y cancelación de peticiones viejas.
 *
 *   readonly list = createListController(params => this.api.list(params));
 *
 * Rendimiento:
 *  - debounceTime(300): no se pega al servidor en cada tecla.
 *  - switchMap: si el usuario sigue escribiendo, se CANCELA la petición
 *    anterior (no hay respuestas fuera de orden).
 *  - distinctUntilChanged: misma consulta = no se repite.
 */
export function createListController<T>(
  fetcher: (params: ListParams) => Observable<Paginated<T>>,
  options: ListControllerOptions = {},
) {
  const destroyRef = inject(DestroyRef);
  const route = inject(ActivatedRoute);
  const router = inject(Router);
  const query = route.snapshot.queryParamMap;
  const syncUrl = options.syncUrl ?? true;

  const search = signal(syncUrl ? (query.get('search') ?? '') : '');
  const page = signal(syncUrl ? Number(query.get('page') ?? 1) || 1 : 1);
  const perPage = signal(Number(query.get('per_page') ?? options.perPage ?? 10) || 10);
  const sort = signal(query.get('sort') ?? options.sort ?? 'created_at');
  const direction = signal<SortDirection>((query.get('direction') as SortDirection) ?? options.direction ?? 'desc');
  const filters = signal<Record<string, string>>({});

  const items = signal<T[]>([]);
  const meta = signal<PageMeta | null>(null);
  const loading = signal(true);
  const failed = signal(false);
  const reload$ = new Subject<void>();

  const params: Signal<ListParams> = computed(() => ({
    search: search().trim() || undefined,
    page: page(),
    per_page: perPage(),
    sort: sort(),
    direction: direction(),
    ...filters(),
  }));

  const key = (p: ListParams) => JSON.stringify(p);

  merge(
    toObservable(params).pipe(debounceTime(300), distinctUntilChanged((a, b) => key(a) === key(b))),
    reload$.pipe(map(() => params())),
  )
    .pipe(
      tap((p) => {
        loading.set(true);
        failed.set(false);
        if (syncUrl) {
          void router.navigate([], {
            relativeTo: route,
            queryParams: { search: p.search ?? null, page: p.page !== 1 ? p.page : null, sort: p.sort, direction: p.direction },
            replaceUrl: true,
          });
        }
      }),
      switchMap((p) =>
        fetcher(p).pipe(
          catchError(() => {
            failed.set(true);
            return of(null);
          }),
        ),
      ),
      takeUntilDestroyed(destroyRef),
    )
    .subscribe((result) => {
      loading.set(false);
      if (!result) return;
      // Si borraste el último registro de la última página, retrocede una
      if (result.data.length === 0 && result.meta.page > 1 && result.meta.page > result.meta.last_page) {
        page.set(result.meta.last_page);
        return;
      }
      items.set(result.data);
      meta.set(result.meta);
    });

  return {
    search,
    page,
    perPage,
    sort,
    direction,
    filters,
    items: items.asReadonly(),
    meta: meta.asReadonly(),
    loading: loading.asReadonly(),
    failed: failed.asReadonly(),
    params,
    setSearch(value: string) {
      search.set(value);
      page.set(1);
    },
    setFilter(name: string, value: string | null) {
      filters.update((f) => {
        const next = { ...f };
        if (value) next[name] = value;
        else delete next[name];
        return next;
      });
      page.set(1);
    },
    toggleSort(column: string) {
      if (sort() === column) direction.update((d) => (d === 'asc' ? 'desc' : 'asc'));
      else {
        sort.set(column);
        direction.set('asc');
      }
      page.set(1);
    },
    setPerPage(value: number) {
      perPage.set(value);
      page.set(1);
    },
    reload() {
      reload$.next();
    },
  };
}

export type ListController<T> = ReturnType<typeof createListController<T>>;
