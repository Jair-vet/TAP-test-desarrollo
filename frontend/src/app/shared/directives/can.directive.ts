import { Directive, TemplateRef, ViewContainerRef, effect, inject, input } from '@angular/core';
import { AuthStore } from '../../core/auth/auth.store';
import { ActionKey, SectionKey } from '../../core/models/api.models';

/**
 * Muestra el elemento solo si el usuario tiene el permiso.
 *   <button *appCan="'products:update'">Editar</button>
 * Es reactivo: si cambian los permisos (refresh), la vista se actualiza.
 * Ojo: es UX; la autorización real la valida el backend (middleware permission).
 */
@Directive({ selector: '[appCan]' })
export class CanDirective {
  private readonly store = inject(AuthStore);
  private readonly template = inject(TemplateRef<unknown>);
  private readonly container = inject(ViewContainerRef);
  private rendered = false;

  readonly appCan = input.required<`${SectionKey}:${ActionKey}` | SectionKey>();

  constructor() {
    effect(() => {
      const [section, action] = this.appCan().split(':') as [SectionKey, ActionKey | undefined];
      const allowed = this.store.can(section, action ?? 'view');
      if (allowed && !this.rendered) {
        this.container.createEmbeddedView(this.template);
        this.rendered = true;
      } else if (!allowed && this.rendered) {
        this.container.clear();
        this.rendered = false;
      }
    });
  }
}
