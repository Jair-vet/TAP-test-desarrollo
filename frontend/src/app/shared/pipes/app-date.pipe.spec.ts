import { AppDatePipe } from './app-date.pipe';

describe('AppDatePipe', () => {
  const pipe = new AppDatePipe();

  it('formatea como DD/MM/YYYY HH:MM (requisito del examen)', () => {
    const local = new Date(2026, 10, 5, 9, 7); // 5 nov 2026 09:07 hora local
    expect(pipe.transform(local.toISOString())).toBe('05/11/2026 09:07');
  });

  it('solo fecha', () => {
    expect(pipe.transform(new Date(2026, 0, 2).toISOString(), 'date')).toBe('02/01/2026');
  });

  it('valor vacío', () => {
    expect(pipe.transform(null)).toBe('—');
  });
});
