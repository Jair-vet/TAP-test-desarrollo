import { DIALOG_DATA, DialogRef } from '@angular/cdk/dialog';
import { JsonPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { IconComponent } from '../../shared/ui/icon.component';
import { ModalComponent, ModalFooterComponent } from '../../shared/ui/modal.component';
import { AuditApi } from './audit.api';
import { eventTone } from './audit.tones';

/**
 * Comparación "información anterior" vs "información actual".
 * Los campos que cambiaron se resaltan (rojo = antes, verde = después).
 */
@Component({
  selector: 'app-audit-detail-dialog',
  imports: [ModalComponent, ModalFooterComponent, IconComponent, AppDatePipe, JsonPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-modal title="Detalle de bitácora" [subtitle]="entry() ? entry()!.event_label + ' · ' + entry()!.entity_label + ' ' + (entry()!.entity_code ?? '') : 'Cargando…'" icon="rotate-ccw-clock">
      @if (entry(); as e) {
        <div class="animate-fade-in space-y-5">
          <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-zinc-600 dark:text-zinc-400">
            <span class="badge" [class]="tone(e.event)">{{ e.event_label }}</span>
            <span class="flex items-center gap-1.5"><app-icon name="calendar" [size]="14" /> {{ e.created_at | appDate }}</span>
            <span class="flex items-center gap-1.5"><app-icon name="user-round" [size]="14" /> {{ e.actor.name }} @if (e.actor.email) { ({{ e.actor.email }}) }</span>
            @if (e.ip) { <span class="flex items-center gap-1.5 font-mono text-xs"><app-icon name="smartphone" [size]="14" /> {{ e.ip }}</span> }
          </div>

          @if (rows().length) {
            <div class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-white/[0.06]">
              <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500 dark:bg-white/[0.03]">
                  <tr>
                    <th class="w-1/4 px-4 py-2.5 text-left font-semibold">Campo</th>
                    <th class="px-4 py-2.5 text-left font-semibold">Información anterior</th>
                    <th class="px-4 py-2.5 text-left font-semibold">Información actual</th>
                  </tr>
                </thead>
                <tbody>
                  @for (r of rows(); track r.field; let i = $index) {
                    <tr class="border-t border-zinc-100 animate-fade-up dark:border-white/[0.04]" [style.animation-delay.ms]="i * 35">
                      <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">{{ r.field }}</td>
                      <td class="px-4 py-2.5" [class]="r.changed ? 'bg-red-50 text-red-800 line-through decoration-red-400/60 dark:bg-red-500/10 dark:text-red-300' : 'text-zinc-500'">{{ show(r.before) }}</td>
                      <td class="px-4 py-2.5" [class]="r.changed ? 'bg-emerald-50 font-medium text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-zinc-500'">{{ show(r.after) }}</td>
                    </tr>
                  }
                </tbody>
              </table>
            </div>
          } @else {
            <p class="rounded-xl bg-zinc-50 p-4 text-sm text-zinc-500 dark:bg-white/[0.03]">Evento sin cambios de datos (ej. inicio de sesión).</p>
          }

          @if (e.meta) {
            <details class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-white/[0.06]">
              <summary class="cursor-pointer font-medium">Datos adicionales</summary>
              <pre class="mt-2 overflow-x-auto text-xs text-zinc-600 dark:text-zinc-400">{{ e.meta | json }}</pre>
            </details>
          }
        </div>
      } @else {
        <div class="h-48 skeleton"></div>
      }
      <app-modal-footer>
        <button type="button" class="btn btn-outline" (click)="ref.close()">Cerrar</button>
      </app-modal-footer>
    </app-modal>
  `,
})
export class AuditDetailDialog {
  protected readonly ref = inject(DialogRef);
  private readonly id = inject<string>(DIALOG_DATA);
  protected readonly entry = toSignal(inject(AuditApi).get(this.id));
  protected readonly tone = eventTone;

  /** Todas las llaves de antes y después; las cambiadas primero. */
  protected readonly rows = computed(() => {
    const e = this.entry();
    if (!e) return [];
    const flat = (obj: Record<string, unknown> | null, prefix = ''): Record<string, unknown> =>
      Object.entries(obj ?? {}).reduce<Record<string, unknown>>((acc, [k, v]) => {
        const key = prefix ? `${prefix}.${k}` : k;
        if (v && typeof v === 'object' && !Array.isArray(v)) Object.assign(acc, flat(v as Record<string, unknown>, key));
        else acc[key] = Array.isArray(v) ? v.join(', ') : v;
        return acc;
      }, {});
    const before = flat(e.before);
    const after = flat(e.after);
    const changed = new Set(e.changes.map((c) => c.field));
    const fields = [...new Set([...Object.keys(before), ...Object.keys(after)])];
    return fields
      .map((field) => ({ field, before: before[field], after: after[field], changed: changed.has(field) }))
      .sort((a, b) => Number(b.changed) - Number(a.changed));
  });

  show(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Sí' : 'No';
    return String(value);
  }
}
