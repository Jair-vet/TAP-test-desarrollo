/**
 * Base de la API. Relativa porque Nginx sirve front y API en el MISMO origen
 * (sin CORS). En desarrollo, `ng serve` usa proxy.conf.json → localhost:8080.
 *
 * Si te piden apuntar a otra API (ej. otro servidor), cambia esta constante
 * y habilita CORS_ALLOWED_ORIGINS en el backend.
 */
export const API_URL = '/api/v1';
