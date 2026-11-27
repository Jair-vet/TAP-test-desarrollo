import { animate, style, transition, trigger } from '@angular/animations';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { ToastKind, ToastService } from '../../core/ui/toast.service';
import { IconComponent } from './icon.component';

const ICONS: Record<ToastKind, string> = {
  success: 'circle-check',
  error: 'circle-x',
  warning: 'triangle-exclamation-point',
  info: 'info',
};

/** Pila de notificaciones con animación de entrada/salida (@angular/animations). */
@Component({
  selector: 'app-toast-stack',
  imports: [IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  animations: [
    trigger('toast', [
      transition(':enter', [
        style({ opacity: 0, transform: 'translateY(12px) scale(0.96)' }),
        animate('320ms cubic-bezier(0.16, 1, 0.3, 1)', style({ opacity: 1, transform: 'none' })),
      ]),
      transition(':leave', [animate('180ms ease-in', style({ opacity: 0, transform: 'translateX(24px)' }))]),
    ]),
  ],
  template: `
    <div class="pointer-events-none fixed bottom-4 right-4 z-[1100] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-2" aria-live="polite" role="status">
      @for (t of toast.toasts(); track t.id) {
        <div @toast class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-lg shadow-zinc-900/10 dark:bg-ink-800 dark:shadow-black/40"
          [class]="borders[t.kind]">
          <app-icon [name]="icons[t.kind]" [size]="20" [class]="colors[t.kind]" />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ t.title }}</p>
            @if (t.message) { <p class="mt-0.5 break-words text-sm text-zinc-600 dark:text-zinc-400">{{ t.message }}</p> }
          </div>
          <button type="button" class="rounded-md p-0.5 text-zinc-400 transition hover:text-zinc-700 dark:hover:text-white" (click)="toast.dismiss(t.id)" aria-label="Cerrar notificación">
            <app-icon name="x" [size]="16" />
          </button>
        </div>
      }
    </div>
  `,
})
export class ToastStackComponent {
  protected readonly toast = inject(ToastService);
  protected readonly icons = ICONS;
  protected readonly colors: Record<ToastKind, string> = {
    success: 'text-emerald-600',
    error: 'text-red-600',
    warning: 'text-amber-500',
    info: 'text-sky-600',
  };
  protected readonly borders: Record<ToastKind, string> = {
    success: 'border-emerald-200 dark:border-emerald-500/30',
    error: 'border-red-200 dark:border-red-500/30',
    warning: 'border-amber-200 dark:border-amber-500/30',
    info: 'border-sky-200 dark:border-sky-500/30',
  };
}
