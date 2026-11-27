import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ExportFormat, ListParams } from '../models/api.models';
import { API_URL } from './api.config';

/**
 * Descarga de PDF/Excel. Se pide como Blob con el token (no con un <a href>,
 * que no puede mandar el header Authorization) y se guarda con el nombre que
 * dicta el servidor en Content-Disposition.
 */
@Injectable({ providedIn: 'root' })
export class DownloadService {
  private readonly http = inject(HttpClient);

  export(resource: string, format: ExportFormat, params: ListParams = {}): Observable<string> {
    let httpParams = new HttpParams().set('format', format);
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== '' && key !== 'page' && key !== 'per_page') {
        httpParams = httpParams.set(key, String(value));
      }
    }

    return this.http
      .get(`${API_URL}/${resource}/export`, { params: httpParams, responseType: 'blob', observe: 'response' })
      .pipe(
        map((response) => {
          const disposition = response.headers.get('Content-Disposition') ?? '';
          const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition);
          const filename = decodeURIComponent(match?.[1] ?? `${resource}.${format}`);
          this.save(response.body as Blob, filename);
          return filename;
        }),
      );
  }

  private save(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1_000);
  }
}
