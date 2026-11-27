import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

/**
 * Validadores reutilizables (espejo de las reglas del backend). Devuelven el
 * mensaje en `server` para que <app-form-error> lo muestre tal cual.
 */

const text = (c: AbstractControl): string => (typeof c.value === 'string' ? c.value : '');

/** `required` deja pasar "   "; este no. */
export const notBlank: ValidatorFn = (c) =>
  text(c).length > 0 && text(c).trim().length === 0 ? { server: 'No puede quedar en blanco.' } : null;

/** Caracteres de control (saltos de línea, tabuladores, NUL…): códigos 0-31 y 127. */
const hasControlChars = (value: string): boolean =>
  [...value].some((ch) => {
    const code = ch.charCodeAt(0);
    return code < 32 || code === 127;
  });

/** Sin etiquetas HTML ni caracteres de control (defensa en profundidad contra XSS/CRLF). */
export const safeText: ValidatorFn = (c) =>
  /[<>]/.test(text(c)) || hasControlChars(text(c)) ? { server: 'No se permiten los caracteres < > ni saltos de línea.' } : null;

/** Nombres de persona: letras (con acentos), espacios, punto, apóstrofo y guion. */
export const personName: ValidatorFn = (c) =>
  text(c) && !/^[\p{L}\p{M}][\p{L}\p{M} .'-]*$/u.test(text(c).trim())
    ? { server: 'Usa solo letras, espacios y . \' -' }
    : null;

/** Nombre de perfil: letras, números, espacios y . _ - */
export const profileName: ValidatorFn = (c) =>
  text(c) && !/^[\p{L}\p{M}\d][\p{L}\p{M}\d ._-]*$/u.test(text(c).trim())
    ? { server: 'Usa solo letras, números, espacios y . _ -' }
    : null;

/** Correo con dominio y TLD (Validators.email acepta "a@b"). */
export const strictEmail: ValidatorFn = (c) =>
  text(c) && !/^[^\s@<>()[\]\\,;:"]+@[^\s@<>()[\]\\,;:"]+\.[A-Za-z]{2,}$/.test(text(c).trim())
    ? { server: 'Escribe un correo válido (ej. nombre@empresa.com).' }
    : null;

/** Código TOTP de 6 dígitos. */
export const totpCode: ValidatorFn = (c): ValidationErrors | null =>
  text(c) && !/^\d{6}$/.test(text(c)) ? { server: 'El código tiene 6 dígitos.' } : null;
