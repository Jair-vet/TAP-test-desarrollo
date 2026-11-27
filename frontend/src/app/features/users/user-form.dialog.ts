import { DIALOG_DATA, DialogRef } from '@angular/cdk/dialog';
import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { CountryCode } from 'libphonenumber-js/min';
import { FormErrorComponent } from '../../shared/ui/form-error.component';
import { IconComponent } from '../../shared/ui/icon.component';
import { ModalComponent, ModalFooterComponent } from '../../shared/ui/modal.component';
import { applyServerErrors } from '../../shared/utils/form-errors';
import { COUNTRIES, formatAsYouType, isValidPhone, splitE164, toE164 } from '../../shared/utils/phone';
import { UserDetail, UsersApi } from './users.api';
import { notBlank, personName, strictEmail } from '../../shared/utils/validators';

const MAX_PHOTO_BYTES = 2 * 1024 * 1024;
const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Alta / edición de usuario.
 * Campos del examen: Nombre*, Foto de perfil*, Usuario (correo único)*,
 * Teléfono opcional con código de país. El código, la fecha y la contraseña
 * los genera el servidor (la contraseña llega por correo).
 */
@Component({
  selector: 'app-user-form-dialog',
  imports: [ReactiveFormsModule, ModalComponent, ModalFooterComponent, FormErrorComponent, IconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './user-form.dialog.html',
})
export class UserFormDialog {
  protected readonly ref = inject<DialogRef<UserDetail>>(DialogRef);
  protected readonly user = inject<UserDetail | null>(DIALOG_DATA);
  private readonly api = inject(UsersApi);

  protected readonly countries = COUNTRIES;
  protected readonly profiles = toSignal(this.api.profileOptions(), { initialValue: [] });
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly preview = signal<string | null>(this.user?.photo_url ?? null);
  protected readonly dragging = signal(false);
  private photoFile: File | null = null;

  private readonly initialPhone = splitE164(this.user?.phone ?? null);

  protected readonly form = inject(NonNullableFormBuilder).group({
    name: [this.user?.name ?? '', [Validators.required, notBlank, Validators.minLength(3), Validators.maxLength(120), personName]],
    email: [this.user?.email ?? '', [Validators.required, strictEmail, Validators.maxLength(150)]],
    country: [this.initialPhone.country as CountryCode],
    phone: [this.initialPhone.national, [Validators.maxLength(25)]],
    photo: [this.user ? 'existing' : '', this.user ? [] : [Validators.required]],
    profile_ids: [this.user?.profiles.map((p) => p.id) ?? ([] as string[])],
  }, { validators: [(group: AbstractControl) => this.phoneValidator(group)] });

  constructor() {
    inject(DestroyRef).onDestroy(() => {
      const url = this.preview();
      if (url?.startsWith('blob:')) URL.revokeObjectURL(url);
    });
  }

  /** Formatea el número mientras se escribe según el país elegido. */
  formatPhone(): void {
    const { phone, country } = this.form.getRawValue();
    const formatted = formatAsYouType(phone, country);
    if (formatted !== phone) this.form.controls.phone.setValue(formatted, { emitEvent: false });
  }

  onFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) this.setPhoto(file);
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(false);
    const file = event.dataTransfer?.files?.[0];
    if (file) this.setPhoto(file);
  }

  toggleProfile(id: string): void {
    const control = this.form.controls.profile_ids;
    const set = new Set(control.value);
    if (set.has(id)) set.delete(id);
    else set.add(id);
    control.setValue([...set]);
    control.markAsDirty();
  }

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    this.error.set(null);
    const v = this.form.getRawValue();
    const input = {
      name: v.name.trim(),
      email: v.email.trim().toLowerCase(),
      phone: v.phone.trim() ? toE164(v.phone, v.country) : null,
      photo: this.photoFile,
      profile_ids: v.profile_ids,
    };
    const request$ = this.user ? this.api.update(this.user.id, input) : this.api.create(input);

    request$.subscribe({
      next: (saved) => this.ref.close(saved),
      error: (e: unknown) => {
        this.saving.set(false);
        this.error.set(applyServerErrors(this.form, e));
      },
    });
  }

  private setPhoto(file: File): void {
    const control = this.form.controls.photo;
    control.markAsTouched();
    if (!PHOTO_TYPES.includes(file.type)) {
      control.setErrors({ server: 'Formato no permitido. Usa JPG, PNG o WebP.' });
      return;
    }
    if (file.size > MAX_PHOTO_BYTES) {
      control.setErrors({ server: 'La foto pesa más de 2 MB.' });
      return;
    }
    const old = this.preview();
    if (old?.startsWith('blob:')) URL.revokeObjectURL(old);
    this.photoFile = file;
    this.preview.set(URL.createObjectURL(file));
    control.setValue(file.name);
  }

  /** Teléfono opcional, pero si se escribe debe ser válido para el país. */
  private phoneValidator(group: AbstractControl): ValidationErrors | null {
    const phone = group.get('phone');
    const country = group.get('country')?.value as CountryCode;
    if (!phone || !phone.value) return null;
    const valid = isValidPhone(phone.value as string, country);
    if (!valid) phone.setErrors({ ...(phone.errors ?? {}), server: 'Número inválido para el país seleccionado.' });
    else if (phone.errors?.['server']) {
      const { server: _ignored, ...rest } = phone.errors;
      phone.setErrors(Object.keys(rest).length ? rest : null);
    }
    return null;
  }
}
