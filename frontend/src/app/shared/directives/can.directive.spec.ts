import { ChangeDetectionStrategy, Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { AuthStore } from '../../core/auth/auth.store';
import { Permissions } from '../../core/models/api.models';
import { CanDirective } from './can.directive';

@Component({
  imports: [CanDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <button *appCan="'products:update'" id="edit">Editar</button>
    <button *appCan="'products:delete'" id="delete">Eliminar</button>
    <a *appCan="'audit'" id="audit">Bitácora</a>
  `,
})
class HostComponent {}

describe('CanDirective (*appCan)', () => {
  const setPermissions = (permissions: Permissions) =>
    TestBed.inject(AuthStore).setSession({
      status: 'authenticated',
      access_token: 'jwt',
      token_type: 'Bearer',
      expires_in: 600,
      user: {
        id: '1', code: 'USR-000001', email: 'a@tap.test', name: 'Ana', photo_url: null, created_at: '',
        must_change_password: false, two_factor_enabled: false, permissions,
      },
    });

  it('muestra solo los botones permitidos y reacciona a cambios de permisos', () => {
    setPermissions({ products: ['view', 'update'] });
    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();
    const el = fixture.nativeElement as HTMLElement;

    expect(el.querySelector('#edit')).not.toBeNull();
    expect(el.querySelector('#delete')).toBeNull();
    expect(el.querySelector('#audit')).toBeNull();

    // Cambian sus perfiles (p. ej. tras un refresh): la vista se actualiza sola
    setPermissions({ products: ['view', 'delete'], audit: ['view'] });
    fixture.detectChanges();
    expect(el.querySelector('#edit')).toBeNull();
    expect(el.querySelector('#delete')).not.toBeNull();
    expect(el.querySelector('#audit')).not.toBeNull();
  });
});
