import { HttpClient, HttpContext } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, shareReplay } from 'rxjs';
import { API_URL } from '../../core/http/api.config';
import { SILENT_LOADING, SKIP_ERROR_TOAST } from '../../core/http/http-context';
import { ActionKey, DataResponse, ListParams, Paginated, SectionKey } from '../../core/models/api.models';
import { toHttpParams } from '../../shared/utils/http-params';

export interface ProfileSection {
  key: SectionKey;
  label: string;
  actions: ActionKey[];
}

export interface Profile {
  id: string;
  code: string;
  name: string;
  description: string | null;
  sections: ProfileSection[];
  users_count?: number;
  created_at: string;
  updated_at: string;
}

export interface SectionCatalogItem {
  key: SectionKey;
  label: string;
  actions: { key: ActionKey; label: string }[];
}

export interface ProfileInput {
  name: string;
  description: string | null;
  sections: { section: SectionKey; actions: ActionKey[] }[];
}

@Injectable({ providedIn: 'root' })
export class ProfilesApi {
  private readonly http = inject(HttpClient);
  private readonly base = `${API_URL}/profiles`;

  /** El catálogo de secciones casi nunca cambia: se pide una vez por sesión. */
  readonly sections$ = this.http.get<DataResponse<SectionCatalogItem[]>>(`${API_URL}/sections`).pipe(
    map((r) => r.data),
    shareReplay({ bufferSize: 1, refCount: false }),
  );

  list(params: ListParams): Observable<Paginated<Profile>> {
    return this.http.get<Paginated<Profile>>(this.base, {
      params: toHttpParams(params),
      context: new HttpContext().set(SILENT_LOADING, true),
    });
  }

  get(id: string): Observable<Profile> {
    return this.http.get<DataResponse<Profile>>(`${this.base}/${id}`).pipe(map((r) => r.data));
  }

  create(input: ProfileInput): Observable<Profile> {
    return this.http
      .post<DataResponse<Profile>>(this.base, input, { context: new HttpContext().set(SKIP_ERROR_TOAST, true) })
      .pipe(map((r) => r.data));
  }

  update(id: string, input: ProfileInput): Observable<Profile> {
    return this.http
      .put<DataResponse<Profile>>(`${this.base}/${id}`, input, { context: new HttpContext().set(SKIP_ERROR_TOAST, true) })
      .pipe(map((r) => r.data));
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}
