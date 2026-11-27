import { Injectable, signal } from '@angular/core';

export type ToastKind = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
  id: number;
  kind: ToastKind;
  title: string;
  message?: string;
}

/** Notificaciones efímeras (se pintan en ToastStackComponent). */
@Injectable({ providedIn: 'root' })
export class ToastService {
  private seq = 0;
  readonly toasts = signal<Toast[]>([]);

  success(title: string, message?: string): void {
    this.push('success', title, message);
  }

  error(title: string, message?: string): void {
    this.push('error', title, message, 7000);
  }

  warning(title: string, message?: string): void {
    this.push('warning', title, message);
  }

  info(title: string, message?: string): void {
    this.push('info', title, message);
  }

  dismiss(id: number): void {
    this.toasts.update((list) => list.filter((t) => t.id !== id));
  }

  private push(kind: ToastKind, title: string, message?: string, ttl = 4500): void {
    const id = ++this.seq;
    // Máximo 4 visibles: los más viejos salen primero
    this.toasts.update((list) => [...list.slice(-3), { id, kind, title, message }]);
    setTimeout(() => this.dismiss(id), ttl);
  }
}
