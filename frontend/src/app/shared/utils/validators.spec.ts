import { FormControl } from '@angular/forms';
import { notBlank, personName, profileName, safeText, strictEmail, totpCode } from './validators';

const check = (validator: typeof notBlank, value: string) => validator(new FormControl(value));

describe('validadores compartidos', () => {
  it('notBlank rechaza solo espacios', () => {
    expect(check(notBlank, '   ')).not.toBeNull();
    expect(check(notBlank, 'Casco')).toBeNull();
  });

  it('safeText rechaza etiquetas y saltos de línea', () => {
    expect(check(safeText, '<script>alert(1)</script>')).not.toBeNull();
    expect(check(safeText, 'línea\r\notra')).not.toBeNull();
    expect(check(safeText, 'Casco clase "E" 3/4')).toBeNull();
  });

  it('personName acepta acentos y rechaza números', () => {
    expect(check(personName, 'María José Núñez-O\'Neil')).toBeNull();
    expect(check(personName, 'Juan123')).not.toBeNull();
  });

  it('profileName acepta letras, números y . _ -', () => {
    expect(check(profileName, 'Almacén_2 nivel-1')).toBeNull();
    expect(check(profileName, 'Perfil <b>')).not.toBeNull();
  });

  it('strictEmail exige dominio con TLD', () => {
    expect(check(strictEmail, 'ana@tap.test')).toBeNull();
    expect(check(strictEmail, 'ana@tap')).not.toBeNull();
  });

  it('totpCode exige 6 dígitos', () => {
    expect(check(totpCode, '123456')).toBeNull();
    expect(check(totpCode, '12a456')).not.toBeNull();
  });
});
