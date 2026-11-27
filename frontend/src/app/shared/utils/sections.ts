import { ActionKey, SectionKey } from '../../core/models/api.models';

/**
 * Identidad visual por sección y por acción (color + ícono), usada en perfiles,
 * inicio y menú. Los colores viven en styles.css (.tone-*).
 */
export interface ToneMeta {
  label: string;
  icon: string;
  tone: string;
}

export const SECTION_META: Record<SectionKey, ToneMeta> = {
  products: { label: 'Productos', icon: 'package', tone: 'tone-products' },
  users: { label: 'Usuarios', icon: 'users', tone: 'tone-users' },
  profiles: { label: 'Perfiles', icon: 'shield-check', tone: 'tone-profiles' },
  audit: { label: 'Bitácora', icon: 'scroll-text', tone: 'tone-audit' },
};

export const ACTION_META: Record<ActionKey, ToneMeta> = {
  view: { label: 'Consultar', icon: 'eye', tone: 'tone-view' },
  create: { label: 'Crear', icon: 'plus', tone: 'tone-create' },
  update: { label: 'Editar', icon: 'pencil', tone: 'tone-update' },
  delete: { label: 'Eliminar', icon: 'trash', tone: 'tone-delete' },
  export: { label: 'Exportar', icon: 'download', tone: 'tone-export' },
};

export const sectionMeta = (key: string): ToneMeta =>
  SECTION_META[key as SectionKey] ?? { label: key, icon: 'layers', tone: 'tone-neutral' };

export const actionMeta = (key: string): ToneMeta =>
  ACTION_META[key as ActionKey] ?? { label: key, icon: 'info', tone: 'tone-neutral' };
