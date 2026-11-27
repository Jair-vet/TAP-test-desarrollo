import { ChangeDetectionStrategy, Component, inject, input } from '@angular/core';
import { ThemeService } from '../../core/ui/theme.service';
import { ParallaxDirective } from '../../shared/directives/parallax.directive';
import { TiltDirective } from '../../shared/directives/tilt.directive';
import { IconComponent } from '../../shared/ui/icon.component';
import { PortSceneComponent } from '../../shared/ui/port-scene.component';

/**
 * Layout de pantallas públicas (login, recuperación, bandeja de correos):
 * panel de marca con escena 3D + parallax, y el formulario en una tarjeta.
 */
@Component({
  selector: 'app-auth-layout',
  imports: [IconComponent, PortSceneComponent, ParallaxDirective, TiltDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
      <!-- Panel de marca -->
      <aside class="relative hidden overflow-hidden bg-ink-1000 p-12 text-white lg:flex lg:flex-col">
        <app-port-scene variant="login" />

        <div class="relative flex items-center gap-3 animate-fade-up">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-brand-400 text-lg font-black text-ink-950 shadow-lg shadow-brand-400/30">T</div>
          <span class="text-lg font-bold tracking-wide">TAP <span class="text-brand-400">Gestión</span></span>
        </div>

        <div class="relative mt-auto max-w-md" appParallax [parallaxDepth]="-8">
          <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand-400 animate-fade-up" style="animation-delay: 80ms">Terminal portuaria</p>
          <h2 class="mt-3 text-4xl font-bold leading-tight tracking-tight animate-fade-up xl:text-5xl" style="animation-delay: 160ms">
            Productos, usuarios y perfiles en <span class="bg-gradient-to-r from-brand-300 to-brand-500 bg-clip-text text-transparent">un solo lugar</span>.
          </h2>
          <p class="mt-4 text-zinc-400 animate-fade-up" style="animation-delay: 240ms">Acceso por perfiles, bitácora de cambios y verificación en dos pasos.</p>
          <ul class="mt-8 grid gap-3 text-sm text-zinc-300">
            @for (f of features; track f.text; let i = $index) {
              <li class="flex items-center gap-3 rounded-xl bg-white/[0.04] px-4 py-3 ring-1 ring-white/[0.06] backdrop-blur animate-fade-up"
                [style.animation-delay.ms]="320 + i * 90">
                <app-icon [name]="f.icon" class="text-brand-400" /> {{ f.text }}
              </li>
            }
          </ul>
        </div>
      </aside>

      <!-- Formulario -->
      <main class="relative flex items-center justify-center overflow-hidden bg-zinc-50 px-5 py-12 sm:px-8 dark:bg-ink-900">
        <div class="pointer-events-none absolute -right-40 top-1/3 h-80 w-80 rounded-full bg-brand-400/15 blur-3xl dark:bg-brand-400/10"></div>
        <div class="pointer-events-none absolute inset-0 bg-grid-dark opacity-40 dark:opacity-0 [mask-image:radial-gradient(60%_60%_at_50%_40%,#000,transparent)]"></div>

        <button type="button" class="btn btn-ghost btn-icon absolute right-4 top-4" (click)="theme.toggle()" aria-label="Cambiar tema">
          <app-icon [name]="theme.theme() === 'dark' ? 'sun' : 'moon'" />
        </button>

        <div class="relative w-full max-w-md">
          <div class="mb-8 flex items-center gap-3 lg:hidden">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-brand-400 font-black text-ink-950">T</div>
            <span class="font-bold">TAP <span class="text-brand-600 dark:text-brand-400">Gestión</span></span>
          </div>

          <div class="card tilt spotlight p-7 shadow-xl shadow-zinc-900/[0.06] animate-scale-in sm:p-9 dark:shadow-black/40" appTilt [tiltMax]="3">
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ title() }}</h1>
            @if (subtitle()) { <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ subtitle() }}</p> }
            <div class="mt-7"><ng-content /></div>
          </div>
        </div>
      </main>
    </div>
  `,
})
export class AuthLayoutComponent {
  protected readonly theme = inject(ThemeService);
  readonly title = input.required<string>();
  readonly subtitle = input<string>();

  protected readonly features = [
    { icon: 'shield-check', text: 'JWT + 2FA (TOTP) + Argon2id' },
    { icon: 'rotate-ccw-clock', text: 'Bitácora antes/después de cada cambio' },
    { icon: 'file-spreadsheet', text: 'Exportación a PDF y Excel' },
  ];
}
