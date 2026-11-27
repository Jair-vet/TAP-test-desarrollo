import { HttpErrorResponse } from '@angular/common/http';
import { FormControl, FormGroup } from '@angular/forms';
import { applyServerErrors } from './form-errors';
import { toHttpParams } from './http-params';

describe('applyServerErrors (422/409 de Laravel → errores por campo)', () => {
  const form = () => new FormGroup({ name: new FormControl(''), email: new FormControl(''), sections: new FormControl([]) });
  const httpError = (status: number, error: object) => new HttpErrorResponse({ status, error });

  it('asigna cada error de validación a su control', () => {
    const f = form();
    const message = applyServerErrors(f, httpError(422, {
      message: 'Los datos enviados no son válidos.',
      code: 'VALIDATION_ERROR',
      errors: { name: ['El nombre es obligatorio.'], 'sections.0.actions': ['Acción inválida.'] },
    }));

    expect(message).toBe('Corrige los campos marcados.');
    expect(f.controls.name.errors).toEqual({ server: 'El nombre es obligatorio.' });
    expect(f.controls.sections.errors).toEqual({ server: 'Acción inválida.' });
    expect(f.controls.name.touched).toBeTrue();
  });

  it('un 409 con `field` marca el campo (correo duplicado)', () => {
    const f = form();
    applyServerErrors(f, httpError(409, { message: 'Ya existe un usuario con ese correo.', code: 'EMAIL_TAKEN', field: 'email' }));
    expect(f.controls.email.errors).toEqual({ server: 'Ya existe un usuario con ese correo.' });
  });

  it('usa alias cuando el nombre del API difiere del control', () => {
    const f = new FormGroup({ current: new FormControl('') });
    applyServerErrors(f, httpError(422, { message: 'x', code: 'VALIDATION_ERROR', errors: { current_password: ['Incorrecta.'] } }), {
      current_password: 'current',
    });
    expect(f.controls.current.errors).toEqual({ server: 'Incorrecta.' });
  });

  it('errores sin campo devuelven el mensaje general', () => {
    expect(applyServerErrors(form(), httpError(500, { message: 'Error interno', code: 'INTERNAL_ERROR' }))).toBe('Error interno');
    expect(applyServerErrors(form(), new Error('x'))).toBe('Ocurrió un error inesperado.');
  });
});

describe('toHttpParams', () => {
  it('omite vacíos y convierte números', () => {
    const params = toHttpParams({ search: '', page: 2, per_page: 10, sort: 'name', direction: undefined });
    expect(params.toString()).toBe('page=2&per_page=10&sort=name');
  });
});
