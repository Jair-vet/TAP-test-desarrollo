import { DOCUMENT } from '@angular/common';
import { Injectable, effect, inject, signal } from '@angular/core';

type Theme = 'light' | 'dark';
const STORAGE_KEY = 'tap.theme';

/**
 * Modo claro/oscuro. Preferencia guardada en localStorage (es solo una
 * comodidad de UI, nunca datos sensibles) con try/catch por si el navegador
 * bloquea el almacenamiento.
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly document = inject(DOCUMENT);
  readonly theme = signal<Theme>(this.initial());
  private animTimer?: ReturnType<typeof setTimeout>;

  constructor() {
    effect(() => {
      const theme = this.theme();
      this.document.documentElement.classList.toggle('dark', theme === 'dark');
      try {
        localStorage.setItem(STORAGE_KEY, theme);
      } catch {
        /* almacenamiento no disponible */
      }
    });
  }

  toggle(): void {
    // La transición de colores solo vive durante el cambio manual (ver `:root.theme-anim` en styles.css)
    const root = this.document.documentElement;
    root.classList.add('theme-anim');
    this.theme.update((t) => (t === 'dark' ? 'light' : 'dark'));
    clearTimeout(this.animTimer);
    this.animTimer = setTimeout(() => root.classList.remove('theme-anim'), 400);
  }

  private initial(): Theme {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'light' || saved === 'dark') return saved;
    } catch {
      /* ignore */
    }
    return this.document.defaultView?.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
}
