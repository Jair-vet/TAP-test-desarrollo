import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { DownloadService } from '../../core/http/download.service';
import { ExportFormat, ListParams } from '../../core/models/api.models';
import { ToastService } from '../../core/ui/toast.service';
import { IconComponent } from './icon.component';

/** Botones "PDF" y "Excel": descargan lo que se ve en la tabla (respeta búsqueda y orden). */
@Component({
  selector: 'app-export-menu',
  imports: [IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="inline-flex overflow-hidden rounded-xl border border-zinc-300 bg-white shadow-sm dark:border-white/10 dark:bg-ink-900" role="group" aria-label="Exportar">
      <button type="button" class="btn btn-ghost h-10 rounded-none border-r border-zinc-200 dark:border-white/10"
        [disabled]="busy() !== null" (click)="download('pdf')" title="Descargar PDF">
        <app-icon [name]="busy() === 'pdf' ? 'loader' : 'file-text'" [size]="16" [class.animate-spin]="busy() === 'pdf'" class="text-red-600" />
        PDF
      </button>
      <button type="button" class="btn btn-ghost h-10 rounded-none" [disabled]="busy() !== null" (click)="download('xlsx')" title="Descargar Excel">
        <app-icon [name]="busy() === 'xlsx' ? 'loader' : 'file-spreadsheet'" [size]="16" [class.animate-spin]="busy() === 'xlsx'" class="text-emerald-600" />
        Excel
      </button>
    </div>
  `,
})
export class ExportMenuComponent {
  private readonly downloads = inject(DownloadService);
  private readonly toast = inject(ToastService);

  readonly resource = input.required<string>();
  readonly params = input<ListParams>({});
  protected readonly busy = signal<ExportFormat | null>(null);

  download(format: ExportFormat): void {
    this.busy.set(format);
    this.downloads.export(this.resource(), format, this.params()).subscribe({
      next: (file) => this.toast.success('Descarga lista', file),
      error: () => this.busy.set(null),
      complete: () => this.busy.set(null),
    });
  }
}
