import { HttpParams } from '@angular/common/http';
import { ListParams } from '../../core/models/api.models';

/** Convierte ListParams en query string omitiendo vacíos. */
export function toHttpParams(params: ListParams): HttpParams {
  let httpParams = new HttpParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      httpParams = httpParams.set(key, String(value));
    }
  }
  return httpParams;
}
