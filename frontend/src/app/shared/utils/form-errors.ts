import { HttpErrorResponse } from '@angular/common/http';
import { FormGroup } from '@angular/forms';
import { ApiErrorBody } from '../../core/models/api.models';

/**
 * Pasa los errores del servidor (422 `errors` o 409 con `field`) a los
 * controles del formulario. Devuelve el mensaje general para mostrarlo arriba.
 *
 * Laravel manda llaves como `sections.0.actions` o `profile_ids.1`: se toma
 * la primera parte para ubicar el control.
 */
export function applyServerErrors(form: FormGroup, error: unknown, aliases: Record<string, string> = {}): string {
  if (!(error instanceof HttpErrorResponse)) return 'Ocurrió un error inesperado.';
  const body = error.error as ApiErrorBody | null;
  if (!body) return 'Ocurrió un error inesperado.';

  const fieldErrors: Record<string, string[]> = { ...(body.errors ?? {}) };
  if (typeof body['field'] === 'string') fieldErrors[body['field'] as string] = [body.message];

  let unmatched = false;
  for (const [key, messages] of Object.entries(fieldErrors)) {
    const root = aliases[key] ?? aliases[key.split('.')[0]] ?? key.split('.')[0];
    const control = form.get(root);
    if (control) {
      control.setErrors({ ...(control.errors ?? {}), server: messages[0] });
      control.markAsTouched();
    } else {
      unmatched = true;
    }
  }

  return unmatched || Object.keys(fieldErrors).length === 0 ? body.message : 'Corrige los campos marcados.';
}
