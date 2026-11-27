import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { map } from 'rxjs';
import { AuthStore } from '../../core/auth/auth.store';
import { API_URL } from '../../core/http/api.config';
import { DataResponse } from '../../core/models/api.models';
import { CountUpDirective } from '../../shared/directives/count-up.directive';
import { ParallaxDirective } from '../../shared/directives/parallax.directive';
import { RevealDirective } from '../../shared/directives/reveal.directive';
import { TiltDirective } from '../../shared/directives/tilt.directive';
import { AppDatePipe } from '../../shared/pipes/app-date.pipe';
import { IconComponent } from '../../shared/ui/icon.component';
import { PortSceneComponent } from '../../shared/ui/port-scene.component';
import { AuditSummary } from '../audit/audit.api';
import { eventTone } from '../audit/audit.tones';

interface DashboardData {
  counts: Partial<Record<'products' | 'users' | 'profiles', number>>;
  recent_activity: AuditSummary[];
}

/** Inicio: contadores de las secciones permitidas + actividad reciente. */
@Component({
  selector: 'app-dashboard-page',
  imports: [RouterLink, IconComponent, AppDatePipe, PortSceneComponent, TiltDirective, ParallaxDirective, RevealDirective, CountUpDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="relative mb-8 min-h-[13rem] overflow-hidden rounded-3xl bg-ink-1000 p-6 text-white shadow-xl shadow-zinc-900/15 ring-1 ring-white/[0.06] sm:p-8 animate-fade-up">
      <app-port-scene variant="compact" />
      <div class="relative max-w-xl" appParallax [parallaxDepth]="-6">
        <p class="text-sm font-medium text-brand-400">{{ greeting() }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">{{ store.user()?.name }}</h1>
        <p class="mt-2 text-sm text-zinc-400">Este es el resumen de las secciones a las que tienes acceso.</p>
        @if (!store.user()?.two_factor_enabled) {
          <a routerLink="/cuenta/seguridad" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/15 backdrop-blur transition hover:-translate-y-px hover:bg-white/15">
            <app-icon name="shield-check" [size]="16" class="text-brand-400" /> Activa la verificación en dos pasos
            <app-icon name="arrow-right" [size]="15" />
          </a>
        }
      </div>
    </section>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" style="perspective: 1200px">
      @for (card of cards(); track card.key; let i = $index) {
        <a [routerLink]="card.path" appTilt [tiltMax]="9"
          class="card tilt spotlight group relative flex items-center gap-5 overflow-hidden p-6 hover:shadow-2xl hover:shadow-zinc-900/10 animate-fade-up dark:hover:shadow-black/50"
          [class]="'tone-' + card.key" [style.animation-delay.ms]="80 + i * 90">
          <div class="tone-tile tilt-pop h-14 w-14 shrink-0 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-6">
            <app-icon [name]="card.icon" [size]="24" />
          </div>
          <div class="tilt-pop flex-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ card.label }}</p>
            @if (data()) {
              <p class="text-4xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white" [appCountUp]="card.value"></p>
            } @else {
              <div class="mt-1 h-9 w-20 skeleton"></div>
            }
          </div>
          <app-icon name="arrow-right" class="text-zinc-300 transition-all duration-300 group-hover:translate-x-1 group-hover:text-brand-500 dark:text-zinc-600" />
          <!-- barra de color de la sección -->
          <span class="absolute inset-x-0 bottom-0 h-1 origin-left scale-x-0 bg-[var(--tone)] transition-transform duration-500 group-hover:scale-x-100"></span>
        </a>
      } @empty {
        <div class="card p-6 text-sm text-zinc-500 sm:col-span-2 xl:col-span-3">Tu perfil aún no tiene secciones asignadas. Pide acceso al administrador.</div>
      }
    </div>

    <section class="card mt-8" appReveal [revealDelay]="150">
      <header class="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-white/[0.06]">
        <h2 class="flex items-center gap-2 font-semibold"><app-icon name="rotate-ccw-clock" [size]="18" /> Actividad reciente</h2>
        @if (store.can('audit')) { <a routerLink="/bitacora" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-400">Ver bitácora →</a> }
      </header>
      <ul class="divide-y divide-zinc-100 dark:divide-white/[0.04]">
        @for (a of data()?.recent_activity ?? []; track a.id; let i = $index) {
          <li class="flex items-center gap-4 px-5 py-3.5 text-sm transition-colors hover:bg-zinc-50 animate-fade-up dark:hover:bg-white/[0.03]" [style.animation-delay.ms]="300 + i * 60">
            <span class="badge shrink-0" [class]="tone(a.event)">{{ a.event_label }}</span>
            <p class="min-w-0 flex-1 truncate">
              <span class="font-medium">{{ a.actor.name }}</span>
              <span class="text-zinc-500"> · {{ a.entity_label }} {{ a.entity_code ?? '' }}</span>
            </p>
            <time class="shrink-0 tabular-nums text-xs text-zinc-400">{{ a.created_at | appDate }}</time>
          </li>
        } @empty {
          <li class="px-5 py-8 text-center text-sm text-zinc-500">Sin actividad para mostrar.</li>
        }
      </ul>
    </section>
  `,
})
export class DashboardPage {
  protected readonly store = inject(AuthStore);
  protected readonly tone = eventTone;
  protected readonly data = toSignal(
    inject(HttpClient).get<DataResponse<DashboardData>>(`${API_URL}/dashboard`).pipe(map((r) => r.data)),
  );

  protected readonly greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
  });

  protected readonly cards = computed(() => {
    const counts = this.data()?.counts ?? {};
    return [
      { key: 'products', label: 'Productos', icon: 'package', path: '/productos', value: counts.products ?? 0 },
      { key: 'users', label: 'Usuarios', icon: 'users', path: '/usuarios', value: counts.users ?? 0 },
      { key: 'profiles', label: 'Perfiles', icon: 'shield-check', path: '/perfiles', value: counts.profiles ?? 0 },
    ].filter((c) => this.store.can(c.key as 'products'));
  });
}
