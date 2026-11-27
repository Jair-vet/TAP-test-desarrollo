/** Contratos de la API (espejo de docs/openapi.yaml). */

export interface PageMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface Paginated<T> {
  data: T[];
  meta: PageMeta;
}

export interface DataResponse<T> {
  data: T;
}

export interface ApiErrorBody {
  message: string;
  code: string;
  errors?: Record<string, string[]>;
  request_id?: string;
  [extra: string]: unknown;
}

export type SortDirection = 'asc' | 'desc';

export interface ListParams {
  search?: string;
  page?: number;
  per_page?: number;
  sort?: string;
  direction?: SortDirection;
  [filter: string]: string | number | undefined;
}

export type ExportFormat = 'pdf' | 'xlsx';

/** Secciones y acciones (deben coincidir con el enum Section/Action del backend). */
export type SectionKey = 'products' | 'users' | 'profiles' | 'audit';
export type ActionKey = 'view' | 'create' | 'update' | 'delete' | 'export';
export type Permissions = Partial<Record<SectionKey, ActionKey[]>>;

export interface AuthUser {
  id: string;
  code: string;
  email: string;
  name: string;
  photo_url: string | null;
  created_at: string;
  must_change_password: boolean;
  two_factor_enabled: boolean;
  permissions: Permissions;
}

export interface SessionPayload {
  status: 'authenticated';
  access_token: string;
  token_type: 'Bearer';
  expires_in: number;
  user: AuthUser;
  message?: string;
  recovery_codes?: string[];
}

export interface TwoFactorRequired {
  status: 'two_factor_required';
  mfa_token: string;
  expires_in: number;
}

export type LoginResponse = SessionPayload | TwoFactorRequired;
