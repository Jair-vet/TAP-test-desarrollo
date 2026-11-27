import { AutoFocusTarget, Dialog, DialogRole } from '@angular/cdk/dialog';
import { ComponentType } from '@angular/cdk/portal';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ConfirmDialogComponent, ConfirmOptions } from '../../shared/ui/confirm-dialog.component';

/** Ancho del modal: crece en pantallas grandes (ver .dialog-* en styles.css). */
export type DialogSize = 'sm' | 'md' | 'lg' | 'xl';

interface OpenOptions {
  size?: DialogSize;
  role?: DialogRole;
  disableClose?: boolean;
  autoFocus?: AutoFocusTarget;
}

/**
 * Fachada sobre CDK Dialog con la configuración visual del proyecto
 * (backdrop con blur, entrada 3D, tamaño responsivo, cierre con Esc/click fuera).
 */
@Injectable({ providedIn: 'root' })
export class DialogService {
  private readonly dialog = inject(Dialog);

  open<R, D = unknown, C = unknown>(component: ComponentType<C>, data?: D, options: OpenOptions = {}): Observable<R | undefined> {
    const { size = 'md', ...config } = options;
    const ref = this.dialog.open<R, D, C>(component, {
      data,
      backdropClass: 'app-backdrop',
      panelClass: ['app-dialog-panel', `dialog-${size}`],
      autoFocus: 'first-tabbable',
      restoreFocus: true,
      ...config,
    });
    return ref.closed;
  }

  /** Modales de solo lectura: el foco va al contenedor (no resalta "Cerrar"). */
  view<R, D = unknown, C = unknown>(component: ComponentType<C>, data?: D, size: DialogSize = 'md'): Observable<R | undefined> {
    return this.open<R, D, C>(component, data, { autoFocus: 'dialog', size });
  }

  confirm(options: ConfirmOptions): Observable<boolean | undefined> {
    return this.open<boolean, ConfirmOptions>(ConfirmDialogComponent, options, { role: 'alertdialog', size: 'sm' });
  }
}
