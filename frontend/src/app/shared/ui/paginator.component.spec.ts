import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideLucideIcons } from '@lucide/angular';
import { APP_ICONS } from '../../app.icons';
import { PaginatorComponent } from './paginator.component';

describe('PaginatorComponent', () => {
  let fixture: ComponentFixture<PaginatorComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [PaginatorComponent], providers: [provideLucideIcons(...APP_ICONS)] });
    fixture = TestBed.createComponent(PaginatorComponent);
  });

  it('muestra el rango y la ventana de páginas con separadores', () => {
    fixture.componentRef.setInput('meta', { page: 6, per_page: 10, total: 200, last_page: 20 });
    fixture.detectChanges();
    const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

    expect(text).toContain('51–60');
    expect(text).toContain('200');
    const pages = [...(fixture.nativeElement as HTMLElement).querySelectorAll('button')].map((b) => b.textContent?.trim()).filter((t) => /^\d+$/.test(t ?? ''));
    expect(pages).toEqual(['1', '5', '6', '7', '20']);
  });

  it('emite el cambio de página', () => {
    fixture.componentRef.setInput('meta', { page: 1, per_page: 10, total: 30, last_page: 3 });
    fixture.detectChanges();
    let emitted = 0;
    fixture.componentInstance.pageChange.subscribe((p) => (emitted = p));

    (fixture.nativeElement as HTMLElement).querySelector<HTMLButtonElement>('button[aria-label="Página siguiente"]')?.click();
    expect(emitted).toBe(2);
  });
});
