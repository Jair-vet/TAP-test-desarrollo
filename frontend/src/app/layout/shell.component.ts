import { BreakpointObserver } from '@angular/cdk/layout';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { AuthService } from '../core/auth/auth.service';
import { AuthStore } from '../core/auth/auth.store';
import { SectionKey } from '../core/models/api.models';
import { ThemeService } from '../core/ui/theme.service';
import { IconComponent } from '../shared/ui/icon.component';
import { AvatarComponent } from '../shared/ui/states.component';
import { MailboxService } from '../features/mailbox/mailbox.service';

interface NavItem {
  label: string;
  icon: string;
  path: string;
  section?: SectionKey;
}

/** Alto de cada opción del menú + separación (px); mueve el indicador deslizante. */
const NAV_STEP = 48;

/**
 * Layout principal: barra lateral (solo con las secciones permitidas por el
 * perfil) con indicador animado, barra superior con título de la sección,
 * usuario, tema y salir; y el <router-outlet>. En móvil el menú es un cajón.
 */
@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, IconComponent, AvatarComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './shell.component.html',
})
export class ShellComponent {
  protected readonly store = inject(AuthStore);
  protected readonly theme = inject(ThemeService);
  protected readonly mailbox = inject(MailboxService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  private readonly items: NavItem[] = [
    { label: 'Inicio', icon: 'layout-dashboard', path: '/' },
    { label: 'Productos', icon: 'package', path: '/productos', section: 'products' },
    { label: 'Usuarios', icon: 'users', path: '/usuarios', section: 'users' },
    { label: 'Perfiles', icon: 'shield-check', path: '/perfiles', section: 'profiles' },
    { label: 'Bitácora', icon: 'scroll-text', path: '/bitacora', section: 'audit' },
  ];

  /** Menú filtrado por permisos (reactivo: si cambian los perfiles, cambia el menú) */
  protected readonly nav = computed(() => {
    this.store.user();
    return this.items.filter((i) => !i.section || this.store.can(i.section));
  });

  private readonly url = toSignal(
    this.router.events.pipe(
      filter((e) => e instanceof NavigationEnd),
      map(() => this.router.url),
      startWith(this.router.url),
    ),
    { initialValue: this.router.url },
  );

  /** Índice de la opción activa → posición del indicador deslizante (-1 = ninguna) */
  protected readonly activeIndex = computed(() => {
    const path = this.url().split('?')[0];
    return this.nav().findIndex((i) => (i.path === '/' ? path === '/' : path.startsWith(i.path)));
  });
  protected readonly indicatorOffset = computed(() => Math.max(0, this.activeIndex()) * NAV_STEP);

  /** Título de la sección actual (sale del `title` de la ruta hija: "Productos · TAP") */
  protected readonly pageTitle = computed(() => {
    this.url();
    let snapshot = this.router.routerState.snapshot.root;
    while (snapshot.firstChild) snapshot = snapshot.firstChild;
    return (snapshot.title ?? 'Inicio').split(' · ')[0];
  });

  protected readonly isMobile = toSignal(
    inject(BreakpointObserver).observe('(max-width: 1023px)').pipe(map((r) => r.matches)),
    { initialValue: false },
  );
  protected readonly drawerOpen = signal(false);

  constructor() {
    this.router.events.pipe(filter((e) => e instanceof NavigationEnd)).subscribe(() => this.drawerOpen.set(false));
  }

  logout(): void {
    this.auth.logout();
  }
}
