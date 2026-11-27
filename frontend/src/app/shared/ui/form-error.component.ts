import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { AbstractControl } from '@angular/forms';
import { of, startWith, switchMap } from 'rxjs';
import { IconComponent } from './icon.component';

const MESSAGES: Record<string, (e: unknown) => string> = {
  required: () => 'Este campo es obligatorio.',
  email: () => 'Escribe un correo válido.',
  minlength: (e) => `Mínimo ${(e as { requiredLength: number }).requiredLength} caracteres.`,
  maxlength: (e) => `Máximo ${(e as { requiredLength: number }).requiredLength} caracteres.`,
  pattern: () => 'El formato no es válido.',
  min: (e) => `El valor mínimo es ${(e as { min: number }).min}.`,
  max: (e) => `El valor máximo es ${(e as { max: number }).max}.`,
};

/**
 * Muestra el primer error de un control: validación local o la que mandó el
 * servidor (los 422 de Laravel se asignan como `{ server: 'mensaje' }`).
 *
 * Compatible con OnPush: escucha `control.events` (Angular 18+), que emite
 * cambios de valor, estado y "touched" (ej. markAllAsTouched al enviar).
 */
@Component({
  selector: 'app-form-error',
  imports: [IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (message(); as m) {
      <p class="error-text" role="alert"><app-icon name="circle-exclamation-point" [size]="13" /> {{ m }}</p>
    }
  `,
})
export class FormErrorComponent {
  readonly control = input.required<AbstractControl | null>();

  private readonly changes = toSignal(
    toObservable(this.control).pipe(switchMap((c) => (c ? c.events.pipe(startWith(null)) : of(null)))),
  );

  protected readonly message = computed(() => {
    this.changes();
    const control = this.control();
    if (!control?.errors || !(control.touched || control.dirty)) return null;
    const [key, value] = Object.entries(control.errors)[0];
    if (typeof value === 'string') return value;
    return MESSAGES[key]?.(value) ?? 'Valor inválido.';
  });
}
