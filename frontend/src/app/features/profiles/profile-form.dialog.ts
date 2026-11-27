import { DIALOG_DATA, DialogRef } from '@angular/cdk/dialog';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActionKey, SectionKey } from '../../core/models/api.models';
import { FormErrorComponent } from '../../shared/ui/form-error.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { ModalComponent, ModalFooterComponent } from '../../shared/ui/modal.component';
import { applyServerErrors } from '../../shared/utils/form-errors';
import { actionMeta, sectionMeta } from '../../shared/utils/sections';
import { notBlank, profileName, safeText } from '../../shared/utils/validators';
import { Profile, ProfilesApi } from './profiles.api';

type Matrix = Partial<Record<SectionKey, ActionKey[]>>;

/**
 * Alta / edición de perfil: cada sección es una fila con su color e ícono y
 * las acciones son interruptores de color (Consultar, Crear, Editar…).
 * Regla (igual que el backend): activar cualquier acción activa "Consultar";
 * desactivar "Consultar" quita toda la sección.
 */
@Component({
  selector: 'app-profile-form-dialog',
  imports: [ReactiveFormsModule, ModalComponent, ModalFooterComponent, FormErrorComponent, IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-modal [title]="profile ? 'Editar perfil' : 'Nuevo perfil'" [subtitle]="profile?.code ?? 'Define nombre y permisos'"
      [icon]="profile ? 'pencil' : 'shield-check'">
      <form id="profile-form" [formGroup]="form" (ngSubmit)="save()" class="space-y-6" novalidate>
        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label class="label" for="name">Nombre <span class="text-red-500">*</span></label>
            <input id="name" cdkFocusInitial class="input" formControlName="name" maxlength="60" placeholder="Ej. Almacén" />
            <app-form-error [control]="form.controls.name" />
          </div>
          <div>
            <label class="label" for="description">Descripción</label>
            <input id="description" class="input" formControlName="description" maxlength="255" placeholder="Opcional" />
            <app-form-error [control]="form.controls.description" />
          </div>
        </div>

        <div>
          <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
              <span class="label mb-0">Secciones y acciones <span class="text-red-500">*</span></span>
              <p class="hint mt-0.5">Activa lo que este perfil puede hacer. Cualquier acción incluye "Consultar".</p>
            </div>
            <span class="badge badge-brand">{{ selectedCount() }} de {{ catalog().length }} secciones</span>
          </div>

          <div class="grid gap-3">
            @for (s of catalog(); track s.key; let i = $index) {
              <div class="tone-bar flex flex-col gap-3 rounded-2xl border p-4 transition-all duration-300 animate-fade-up sm:flex-row sm:items-center"
                [class]="sectionMeta(s.key).tone + (has(s.key)
                  ? ' border-transparent bg-[color-mix(in_oklab,var(--tone)_7%,white)] dark:bg-[color-mix(in_oklab,var(--tone)_10%,transparent)]'
                  : ' border-zinc-200 dark:border-white/[0.07]')"
                [style.animation-delay.ms]="i * 50">
                <div class="flex min-w-44 items-center gap-3">
                  <span class="tone-tile h-10 w-10 shrink-0 transition-transform duration-300" [class.scale-110]="has(s.key)">
                    <app-icon [name]="sectionMeta(s.key).icon" [size]="18" />
                  </span>
                  <div>
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ s.label }}</p>
                    <p class="text-xs text-zinc-500">{{ has(s.key) ? (matrix()[s.key]?.length ?? 0) + ' acción(es)' : 'Sin acceso' }}</p>
                  </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:ml-auto sm:justify-end" role="group" [attr.aria-label]="'Acciones en ' + s.label">
                  @for (a of s.actions; track a.key) {
                    <button type="button" class="toggle-pill" [class]="actionMeta(a.key).tone"
                      [attr.aria-pressed]="checked(s.key, a.key)" (click)="toggle(s.key, a.key)"
                      [attr.aria-label]="a.label + ' en ' + s.label">
                      <app-icon [name]="actionMeta(a.key).icon" [size]="13" /> {{ a.label }}
                    </button>
                  }
                </div>
              </div>
            }
          </div>
          @if (sectionsTouched() && selectedCount() === 0) {
            <p class="error-text"><app-icon name="circle-exclamation-point" [size]="13" /> Selecciona al menos una sección.</p>
          }
        </div>

        @if (error()) {
          <div class="flex items-center gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-700 animate-fade-in dark:bg-red-500/10 dark:text-red-300" role="alert">
            <app-icon name="circle-exclamation-point" [size]="17" /> {{ error() }}
          </div>
        }
      </form>
      <app-modal-footer>
        <button type="button" class="btn btn-outline" (click)="ref.close()">Cancelar</button>
        <button type="submit" form="profile-form" class="btn btn-primary" [disabled]="saving()">
          @if (saving()) { <app-icon name="loader" [size]="16" class="animate-spin" /> Guardando… }
          @else { <app-icon name="circle-check" [size]="16" /> {{ profile ? 'Guardar cambios' : 'Crear perfil' }} }
        </button>
      </app-modal-footer>
    </app-modal>
  `,
})
export class ProfileFormDialog {
  protected readonly ref = inject<DialogRef<Profile>>(DialogRef);
  protected readonly profile = inject<Profile | null>(DIALOG_DATA);
  private readonly api = inject(ProfilesApi);

  protected readonly catalog = toSignal(this.api.sections$, { initialValue: [] });
  protected readonly sectionMeta = sectionMeta;
  protected readonly actionMeta = actionMeta;

  protected readonly matrix = signal<Matrix>(
    Object.fromEntries((this.profile?.sections ?? []).map((s) => [s.key, [...s.actions]])) as Matrix,
  );
  protected readonly selectedCount = computed(() => Object.values(this.matrix()).filter((a) => a && a.length).length);
  protected readonly sectionsTouched = signal(false);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = inject(NonNullableFormBuilder).group({
    name: [this.profile?.name ?? '', [Validators.required, notBlank, Validators.minLength(3), Validators.maxLength(60), profileName]],
    description: [this.profile?.description ?? '', [Validators.maxLength(255), safeText]],
  });

  has = (section: SectionKey) => (this.matrix()[section]?.length ?? 0) > 0;
  checked = (section: SectionKey, action: ActionKey) => this.matrix()[section]?.includes(action) ?? false;

  toggle(section: SectionKey, action: ActionKey): void {
    this.sectionsTouched.set(true);
    this.matrix.update((m) => {
      const current = new Set(m[section] ?? []);
      if (current.has(action)) {
        if (action === 'view') current.clear();
        else current.delete(action);
      } else {
        current.add(action);
        current.add('view');
      }
      return { ...m, [section]: [...current] };
    });
  }

  save(): void {
    this.sectionsTouched.set(true);
    if (this.form.invalid || this.selectedCount() === 0) {
      this.form.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    this.error.set(null);
    const { name, description } = this.form.getRawValue();
    const sections = (Object.entries(this.matrix()) as [SectionKey, ActionKey[]][])
      .filter(([, actions]) => actions.length > 0)
      .map(([section, actions]) => ({ section, actions }));

    const input = { name: name.trim(), description: description.trim() || null, sections };
    const request$ = this.profile ? this.api.update(this.profile.id, input) : this.api.create(input);
    request$.subscribe({
      next: (saved) => this.ref.close(saved),
      error: (e: unknown) => {
        this.saving.set(false);
        this.error.set(applyServerErrors(this.form, e));
      },
    });
  }
}
