import { Pipe, PipeTransform } from '@angular/core';
import { parsePhoneNumber } from 'libphonenumber-js/min';

/** +523141234567 → +52 314 123 4567 (formato internacional legible). */
@Pipe({ name: 'phone' })
export class PhonePipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    if (!value) return '—';
    try {
      return parsePhoneNumber(value).formatInternational();
    } catch {
      return value;
    }
  }
}
