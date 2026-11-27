import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { DialogService } from '../../core/ui/dialog.service';
import { CanDirective } from '../../shared/directives/can.directive';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { ExportMenuComponent } from '../../shared/ui/export-menu.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { PaginatorComponent } from '../../shared/ui/paginator.component';
import { SearchInputComponent } from '../../shared/ui/search-input.component';
import { SortHeaderComponent } from '../../shared/ui/sort-header.component';
import { EmptyStateComponent, PageHeaderComponent, TableSkeletonComponent } from '../../shared/ui/states.component';
import { createListController } from '../../shared/utils/list-controller';
import { AuditApi, AuditSummary } from './audit.api';
import { AuditDetailDialog } from './audit-detail.dialog';
import { eventTone } from './audit.tones';

/**
 * Bitácora: "guardar la información anterior para compararla con la actual".
 * Cada fila abre un modal con la comparación antes/después campo por campo.
 */
@Component({
  selector: 'app-audit-page',
  imports: [
    AppDatePipe, CanDirective, IconComponent, PaginatorComponent, SearchInputComponent, SortHeaderComponent,
    PageHeaderComponent, EmptyStateComponent, TableSkeletonComponent, ExportMenuComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header eyebrow="Auditoría" title="Bitácora de cambios" subtitle="Quién cambió qué, cuándo y desde dónde; con la información anterior y la actual.">
      <app-export-menu *appCan="'audit:export'" resource="audit-logs" [params]="list.params()" />
    </app-page-header>

    <section class="card overflow-hidden animate-fade-up" style="animation-delay: 60ms">
      <div class="flex flex-col gap-3 border-b border-zinc-100 p-4 lg:flex-row lg:items-center dark:border-white/[0.07]">
        <app-search-input [value]="list.search()" (valueChange)="list.setSearch($event)" placeholder="Buscar por código, usuario o correo…" />
        <div class="grid gap-2 sm:grid-cols-2 lg:flex">
          <select class="input h-11 lg:w-48" aria-label="Filtrar por registro" (change)="list.setFilter('entity_type', $any($event.target).value)">
            <option value="">Todos los registros</option>
            @for (e of catalog()?.entities ?? []; track e.key) { <option [value]="e.key">{{ e.label }}</option> }
          </select>
          <select class="input h-11 lg:w-56" aria-label="Filtrar por evento" (change)="list.setFilter('event', $any($event.target).value)">
            <option value="">Todos los eventos</option>
            @for (e of catalog()?.events ?? []; track e.key) { <option [value]="e.key">{{ e.label }}</option> }
          </select>
        </div>
        <div class="text-xs text-zinc-400 lg:ml-auto">
          @if (list.loading()) {
            <span class="flex items-center gap-2 animate-fade-in"><app-icon name="loader" [size]="14" class="animate-spin" /> Cargando…</span>
          } @else if (list.meta()) {
            <span class="animate-fade-in">{{ list.meta()?.total }} evento(s)</span>
          }
        </div>
      </div>

      <!-- Altura acorde a la pantalla: la tabla hace scroll por dentro con el encabezado fijo -->
      <div class="table-wrap table-scroll" style="--table-offset: 23rem">
        <table class="table table-responsive">
          <thead>
            <tr>
              <th appSortHeader="created_at" [sort]="list.sort()" [direction]="list.direction()" (sortChange)="list.toggleSort($event)">Fecha</th>
              <th appSortHeader="event" [sort]="list.sort()" [direction]="list.direction()" (sortChange)="list.toggleSort($event)">Evento</th>
              <th appSortHeader="entity_type" [sort]="list.sort()" [direction]="list.direction()" (sortChange)="list.toggleSort($event)">Registro</th>
              <th>Usuario</th>
              <th class="hidden lg:table-cell">IP</th>
              <th class="w-px"></th>
            </tr>
          </thead>
          @if (list.loading() && list.items().length === 0) {
            <tbody [appTableSkeleton]="10" [cols]="6"></tbody>
          } @else {
            <tbody [class.opacity-60]="list.loading()" class="transition-opacity">
              @for (a of list.items(); track a.id; let i = $index) {
                <tr class="row-enter row-click" [style.--i]="i" tabindex="0" (click)="open(a)" (keydown.enter)="open(a)"
                  [attr.aria-label]="'Ver detalle: ' + a.event_label + ' ' + a.entity_label + ' ' + (a.entity_code ?? '')">
                  <td data-label="Fecha" class="whitespace-nowrap tabular-nums text-zinc-600 dark:text-zinc-400">{{ a.created_at | appDate }}</td>
                  <td data-label="Evento"><span class="badge" [class]="tone(a.event)">{{ a.event_label }}</span></td>
                  <td data-label="Registro">
                    <span class="text-zinc-500">{{ a.entity_label }}</span>
                    @if (a.entity_code) { <span class="code-chip ml-1.5">{{ a.entity_code }}</span> }
                    @if (a.changes_count) { <span class="ml-1.5 text-xs text-zinc-400">· {{ a.changes_count }} cambio(s)</span> }
                  </td>
                  <td data-label="Usuario">
                    <p class="font-medium">{{ a.actor.name }}</p>
                    @if (a.actor.email) { <p class="text-xs text-zinc-500">{{ a.actor.email }}</p> }
                  </td>
                  <td data-label="IP" class="hidden font-mono text-xs text-zinc-500 lg:table-cell">{{ a.ip ?? '—' }}</td>
                  <td class="cell-actions"><app-icon name="chevron-right" [size]="16" class="text-zinc-400" /></td>
                </tr>
              }
            </tbody>
          }
        </table>

        @if (!list.loading() && list.failed()) {
          <app-empty-state icon="circle-x" tone="error" title="No se pudo cargar la bitácora" actionLabel="Reintentar" (action)="list.reload()" />
        } @else if (!list.loading() && list.items().length === 0) {
          <app-empty-state icon="scroll-text" title="Sin registros" message="No hay eventos con esos filtros." />
        }
      </div>
      <div class="border-t border-zinc-100 dark:border-white/[0.07]">
        <app-paginator [meta]="list.meta()" (pageChange)="list.page.set($event)" (perPageChange)="list.setPerPage($event)" />
      </div>
    </section>
  `,
})
export class AuditPage {
  private readonly api = inject(AuditApi);
  private readonly dialogs = inject(DialogService);
  protected readonly catalog = toSignal(this.api.catalog$);
  protected readonly list = createListController((p) => this.api.list(p), { perPage: 25 });
  protected readonly tone = eventTone;

  open(entry: AuditSummary): void {
    this.dialogs.view(AuditDetailDialog, entry.id, 'xl');
  }
}
