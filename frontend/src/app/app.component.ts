import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { LoadingService } from './core/ui/loading.service';
import { ThemeService } from './core/ui/theme.service';
import { ToastStackComponent } from './shared/ui/toast-stack.component';

/**
 * Componente raíz: barra de progreso global de peticiones HTTP, el router y las notificaciones.
 */
@Component({
  selector: 'app-root',
  imports: [RouterOutlet, ToastStackComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <!-- Barra de progreso global (peticiones HTTP en curso) -->
    <div class="pointer-events-none fixed inset-x-0 top-0 z-[1200] h-[3px] overflow-hidden transition-opacity duration-300"
      [class.opacity-0]="!loading.active()" aria-hidden="true">
      <div class="h-full w-1/2 origin-left rounded-full bg-brand-400 shadow-[0_0_12px_#ffd400] animate-progress"></div>
    </div>

    <router-outlet />
    <app-toast-stack />
  `,
})
export class AppComponent {
  protected readonly loading = inject(LoadingService);
  // Instancia el servicio de tema al arrancar (aplica claro/oscuro)
  private readonly theme = inject(ThemeService);
}
