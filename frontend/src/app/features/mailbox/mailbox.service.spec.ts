import { MAILBOX_URL, MailboxService } from './mailbox.service';

/**
 * Después de entrar a la bandeja se regresa a ?next=… Solo se permiten rutas dentro de /mail/:
 * así nadie puede armar un enlace que, tras iniciar sesión, mande al usuario a otro sitio
 * (redirección abierta).
 */
describe('MailboxService.safeNext', () => {
  it('acepta rutas dentro de la bandeja', () => {
    expect(MailboxService.safeNext('/mail/')).toBe('/mail/');
    expect(MailboxService.safeNext('/mail/view/abc123')).toBe('/mail/view/abc123');
  });

  it('sin destino, va a la bandeja', () => {
    expect(MailboxService.safeNext(undefined)).toBe(MAILBOX_URL);
    expect(MailboxService.safeNext(null)).toBe(MAILBOX_URL);
    expect(MailboxService.safeNext('')).toBe(MAILBOX_URL);
  });

  it('rechaza destinos fuera de /mail/ (redirección abierta)', () => {
    expect(MailboxService.safeNext('https://sitio-malicioso.com')).toBe(MAILBOX_URL);
    expect(MailboxService.safeNext('//sitio-malicioso.com/mail/')).toBe(MAILBOX_URL);
    expect(MailboxService.safeNext('/mail//sitio-malicioso.com')).toBe(MAILBOX_URL);
    expect(MailboxService.safeNext('/productos')).toBe(MAILBOX_URL);
  });
});
