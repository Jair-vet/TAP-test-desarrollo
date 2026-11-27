import { COUNTRIES, isValidPhone, splitE164, toE164 } from './phone';

describe('utilidades de teléfono (código de país)', () => {
  it('México aparece primero', () => {
    expect(COUNTRIES[0].code).toBe('MX');
    expect(COUNTRIES[0].dial).toBe('+52');
  });

  it('valida y convierte a E.164', () => {
    expect(isValidPhone('314 123 4567', 'MX')).toBeTrue();
    expect(isValidPhone('123', 'MX')).toBeFalse();
    expect(toE164('314 123 4567', 'MX')).toBe('+523141234567');
  });

  it('separa un E.164 existente para editarlo', () => {
    const parts = splitE164('+523141234567');
    expect(parts.country).toBe('MX');
    expect(parts.national.replace(/\D/g, '')).toBe('3141234567');
  });
});
