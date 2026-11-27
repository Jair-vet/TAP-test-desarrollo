import { HttpClient, HttpContext } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, shareReplay } from 'rxjs';
import { API_URL } from '../../core/http/api.config';
import { SILENT_LOADING } from '../../core/http/http-context';
import { DataResponse, ListParams, Paginated } from '../../core/models/api.models';
import { toHttpParams } from '../../shared/utils/http-params';

/** Quién hizo el cambio (null en eventos del sistema o intentos anónimos). */
export interface AuditActor {
  id: string | null;
  code: string | null;
  name: string;
  email: string | null;
}

export interface AuditSummary {
  id: string;
  event: string;
  event_label: string;
  entity_type: string;
  entity_label: string;
  entity_id: string | null;
  entity_code: string | null;
  actor: AuditActor;
  changes_count: number;
  ip: string | null;
  created_at: string;
}

export interface AuditChange {
  field: string;
  before: unknown;
  after: unknown;
}

/** Registro completo: información anterior (before) vs actual (after) y la lista de cambios. */
export interface AuditDetail extends AuditSummary {
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  changes: AuditChange[];
  meta: Record<string, unknown> | null;
  user_agent: string | null;
}

export interface CatalogItem {
  key: string;
  label: string;
}

/**
 * Cliente HTTP de la bitácora (GET /audit-logs, /audit-logs/{id} y /audit-logs/catalog).
 * El catálogo de filtros se pide una sola vez y se comparte (shareReplay).
 */
@Injectable({ providedIn: 'root' })
export class AuditApi {
  private readonly http = inject(HttpClient);
  private readonly base = `${API_URL}/audit-logs`;

  readonly catalog$ = this.http.get<DataResponse<{ events: CatalogItem[]; entities: CatalogItem[] }>>(`${this.base}/catalog`).pipe(
    map((r) => r.data),
    shareReplay({ bufferSize: 1, refCount: false }),
  );

  list(params: ListParams): Observable<Paginated<AuditSummary>> {
    return this.http.get<Paginated<AuditSummary>>(this.base, {
      params: toHttpParams(params),
      context: new HttpContext().set(SILENT_LOADING, true),
    });
  }

  get(id: string): Observable<AuditDetail> {
    return this.http.get<DataResponse<AuditDetail>>(`${this.base}/${id}`).pipe(map((r) => r.data));
  }
}
