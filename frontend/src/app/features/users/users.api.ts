import { HttpClient, HttpContext } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, shareReplay } from 'rxjs';
import { API_URL } from '../../core/http/api.config';
import { SILENT_LOADING, SKIP_ERROR_TOAST } from '../../core/http/http-context';
import { DataResponse, ListParams, Paginated } from '../../core/models/api.models';
import { toHttpParams } from '../../shared/utils/http-params';

export interface UserSummary {
  id: string;
  code: string;
  email: string;
  name: string;
  photo_url: string | null;
  created_at: string;
}

export interface ProfileRef {
  id: string;
  code: string;
  name: string;
}

export interface UserDetail extends UserSummary {
  phone: string | null;
  profiles: ProfileRef[];
  two_factor_enabled: boolean;
  must_change_password: boolean;
  last_login_at: string | null;
  updated_at: string;
}

export interface UserInput {
  name: string;
  email: string;
  phone: string | null;
  photo: File | null;
  profile_ids: string[];
}

@Injectable({ providedIn: 'root' })
export class UsersApi {
  private readonly http = inject(HttpClient);
  private readonly base = `${API_URL}/users`;
  private optionsCache: { at: number; data$: Observable<ProfileRef[]> } | null = null;

  list(params: ListParams): Observable<Paginated<UserSummary>> {
    return this.http.get<Paginated<UserSummary>>(this.base, {
      params: toHttpParams(params),
      context: new HttpContext().set(SILENT_LOADING, true),
    });
  }

  get(id: string): Observable<UserDetail> {
    return this.http.get<DataResponse<UserDetail>>(`${this.base}/${id}`).pipe(map((r) => r.data));
  }

  /** Alta: multipart/form-data por la foto (el navegador pone el boundary). */
  create(input: UserInput): Observable<UserDetail> {
    return this.http
      .post<DataResponse<UserDetail>>(this.base, this.toFormData(input), { context: new HttpContext().set(SKIP_ERROR_TOAST, true) })
      .pipe(map((r) => r.data));
  }

  /** Edición: POST + _method=PUT (PHP no procesa multipart en PUT nativo). */
  update(id: string, input: UserInput): Observable<UserDetail> {
    const body = this.toFormData(input);
    body.append('_method', 'PUT');
    return this.http
      .post<DataResponse<UserDetail>>(`${this.base}/${id}`, body, { context: new HttpContext().set(SKIP_ERROR_TOAST, true) })
      .pipe(map((r) => r.data));
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.base}/${id}`);
  }

  /** Perfiles para el selector. Caché en memoria 60 s (cambian poco). */
  profileOptions(): Observable<ProfileRef[]> {
    if (!this.optionsCache || Date.now() - this.optionsCache.at > 60_000) {
      this.optionsCache = {
        at: Date.now(),
        data$: this.http.get<DataResponse<ProfileRef[]>>(`${API_URL}/profiles/options`).pipe(
          map((r) => r.data),
          shareReplay({ bufferSize: 1, refCount: false }),
        ),
      };
    }
    return this.optionsCache.data$;
  }

  invalidateProfileOptions(): void {
    this.optionsCache = null;
  }

  private toFormData(input: UserInput): FormData {
    const data = new FormData();
    data.append('name', input.name);
    data.append('email', input.email);
    data.append('phone', input.phone ?? '');
    if (input.photo) data.append('photo', input.photo);
    input.profile_ids.forEach((id) => data.append('profile_ids[]', id));
    return data;
  }
}
