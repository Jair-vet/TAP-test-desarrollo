import { HttpClient, HttpContext } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { API_URL } from '../../core/http/api.config';
import { SILENT_LOADING, SKIP_ERROR_TOAST } from '../../core/http/http-context';
import { DataResponse, ListParams, Paginated } from '../../core/models/api.models';
import { toHttpParams } from '../../shared/utils/http-params';

export interface Product {
  id: string;
  code: string;
  name: string;
  brand: string;
  price: number;
  created_at: string;
  updated_at: string;
}

export interface ProductInput {
  name: string;
  brand: string;
  price: number | string;
}

/**
 * Acceso a datos de productos (único lugar donde se conocen sus endpoints).
 * Si te piden cambiar una URL del API de productos: aquí.
 */
@Injectable({ providedIn: 'root' })
export class ProductsApi {
  private readonly http = inject(HttpClient);
  private readonly base = `${API_URL}/products`;

  list(params: ListParams): Observable<Paginated<Product>> {
    return this.http.get<Paginated<Product>>(this.base, {
      params: toHttpParams(params),
      context: new HttpContext().set(SILENT_LOADING, true),
    });
  }

  get(id: string): Observable<Product> {
    return this.http.get<DataResponse<Product>>(`${this.base}/${id}`).pipe(map((r) => r.data));
  }

  create(input: ProductInput): Observable<Product> {
    return this.http
      .post<DataResponse<Product>>(this.base, input, { context: new HttpContext().set(SKIP_ERROR_TOAST, true) })
      .pipe(map((r) => r.data));
  }

  update(id: string, input: ProductInput): Observable<Product> {
    return this.http
      .put<DataResponse<Product>>(`${this.base}/${id}`, input, { context: new HttpContext().set(SKIP_ERROR_TOAST, true) })
      .pipe(map((r) => r.data));
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}

