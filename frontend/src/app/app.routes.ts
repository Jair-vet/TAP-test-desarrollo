import { Routes } from '@angular/router';
import { authGuard, guestGuard, permissionGuard } from './core/auth/auth.guards';
import { ShellComponent } from './layout/shell.component';

/**
 * Rutas. Cada pantalla se carga de forma diferida (lazy loading): el usuario
 * solo descarga el JS de las secciones que visita.
 *
 * Para agregar una pantalla nueva protegida por perfil:
 *   { path: 'reportes', canActivate: [permissionGuard('reports')], loadComponent: () => import(...) }
 */
export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    title: 'Iniciar sesión · TAP',
    loadComponent: () => import('./features/auth/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'recuperar-contrasena',
    canActivate: [guestGuard],
    title: 'Recuperar contraseña · TAP',
    loadComponent: () => import('./features/auth/forgot-password.page').then((m) => m.ForgotPasswordPage),
  },
  {
    // Acceso a la bandeja de correos de prueba (Nginx redirige aquí si no hay cookie)
    path: 'correos',
    title: 'Bandeja de correos · TAP',
    loadComponent: () => import('./features/mailbox/mailbox-login.page').then((m) => m.MailboxLoginPage),
  },
  {
    path: '',
    component: ShellComponent,
    canActivate: [authGuard],
    canActivateChild: [authGuard],
    children: [
      {
        path: '',
        title: 'Inicio · TAP',
        loadComponent: () => import('./features/dashboard/dashboard.page').then((m) => m.DashboardPage),
      },
      {
        path: 'productos',
        title: 'Productos · TAP',
        canActivate: [permissionGuard('products')],
        loadComponent: () => import('./features/products/products.page').then((m) => m.ProductsPage),
      },
      {
        path: 'usuarios',
        title: 'Usuarios · TAP',
        canActivate: [permissionGuard('users')],
        loadComponent: () => import('./features/users/users.page').then((m) => m.UsersPage),
      },
      {
        path: 'perfiles',
        title: 'Perfiles · TAP',
        canActivate: [permissionGuard('profiles')],
        loadComponent: () => import('./features/profiles/profiles.page').then((m) => m.ProfilesPage),
      },
      {
        path: 'bitacora',
        title: 'Bitácora · TAP',
        canActivate: [permissionGuard('audit')],
        loadComponent: () => import('./features/audit/audit.page').then((m) => m.AuditPage),
      },
      {
        path: 'cuenta/seguridad',
        title: 'Seguridad · TAP',
        loadComponent: () => import('./features/account/security.page').then((m) => m.SecurityPage),
      },
      {
        path: 'cuenta/cambiar-contrasena',
        title: 'Cambiar contraseña · TAP',
        loadComponent: () => import('./features/account/change-password.page').then((m) => m.ChangePasswordPage),
      },
      {
        path: 'sin-acceso',
        title: 'Sin acceso · TAP',
        loadComponent: () => import('./features/errors/error.page').then((m) => m.ForbiddenPage),
      },
    ],
  },
  {
    path: '**',
    title: 'No encontrado · TAP',
    loadComponent: () => import('./features/errors/error.page').then((m) => m.NotFoundPage),
  },
];
