import { formatDate } from '@angular/common';
import { Pipe, PipeTransform } from '@angular/core';

/**
 * Fechas en el formato que pide el examen: DD/MM/YYYY HH:MM (24 h),
 * en la zona horaria del navegador. La API siempre manda ISO-8601 UTC.
 *   {{ product.created_at | appDate }}  →  25/11/2026 13:45
 */
@Pipe({ name: 'appDate' })
export class AppDatePipe implements PipeTransform {
  transform(value: string | null | undefined, format: 'datetime' | 'date' = 'datetime'): string {
    if (!value) return '—';
    return formatDate(value, format === 'date' ? 'dd/MM/yyyy' : 'dd/MM/yyyy HH:mm', 'en-US');
  }
}
