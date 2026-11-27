/** Color del badge según el tipo de evento de bitácora. */
export function eventTone(event: string): string {
  if (event === 'created' || event === 'login' || event === 'two_factor_enabled') return 'badge-green';
  if (event === 'updated' || event === 'password_changed') return 'badge-blue';
  if (event === 'deleted' || event.includes('failed') || event.includes('locked') || event.includes('reuse')) return 'badge-red';
  return 'badge-gray';
}
