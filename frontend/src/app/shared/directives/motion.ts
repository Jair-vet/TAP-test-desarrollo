/** Utilidades comunes de las directivas de movimiento. */

/** El usuario pidió reducir el movimiento en su sistema operativo. */
export const prefersReducedMotion = (): boolean =>
  typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Pantallas táctiles: sin efectos que dependen del puntero. */
export const isCoarsePointer = (): boolean => typeof matchMedia === 'function' && matchMedia('(pointer: coarse)').matches;

export const motionAllowed = (): boolean => !prefersReducedMotion() && !isCoarsePointer();
