#!/usr/bin/env node
/**
 * Genera la colección de Postman (v2.1) de la API TAP y su entorno local.
 *   node docs/postman/build-collection.mjs
 *
 * Qué trae la colección:
 * - Variables: baseUrl, email, password, accessToken, mfaToken, productId, userId,
 *   profileId, assignProfileId, auditId, userEmail, profileName, mailboxPassword.
 * - "Sesión → Login" guarda el access token automáticamente.
 * - Las altas guardan el id creado para usarlo en ver/editar/eliminar.
 * - CADA petición trae una aserción del código HTTP esperado (y algunas validan
 *   el contenido), así la misma colección sirve de prueba automática con Newman
 *   (`make test-api`, job `e2e` del CI).
 * - Casos negativos del examen: precio con 4 dígitos, alta sin foto, correo y
 *   nombre de perfil duplicados (409), teléfono sin código de país, petición sin
 *   token, registro eliminado (404) e intentos de inyección NoSQL.
 * - Los datos de prueba son únicos por corrida (correo y nombre de perfil con
 *   marca de tiempo), así se puede ejecutar cuantas veces se quiera.
 * - Cada petición incluye ejemplos de respuesta.
 *
 * Se versiona este script (no solo el JSON) para que la colección sea reproducible.
 */
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

/** Archivo de imagen que usan las altas con foto (ruta relativa a docs/postman/). */
const PHOTO = 'foto-ejemplo.png';

const json = (body) => ({ mode: 'raw', raw: JSON.stringify(body, null, 2), options: { raw: { language: 'json' } } });
const url = (path, query = []) => ({
  raw: `{{baseUrl}}${path}${query.length ? '?' + query.map((q) => `${q.key}=${q.value}`).join('&') : ''}`,
  host: ['{{baseUrl}}'],
  path: path.split('/').filter(Boolean),
  query,
});
const q = (key, value, description, disabled = false) => ({ key, value: String(value), description, disabled });
const script = (listen, lines) => ({ listen, script: { type: 'text/javascript', exec: lines } });
const example = (name, status, body) => ({
  name,
  status: { 200: 'OK', 201: 'Created', 204: 'No Content' }[status] ?? String(status),
  code: status,
  _postman_previewlanguage: 'json',
  header: [{ key: 'Content-Type', value: 'application/json' }],
  body: body === undefined ? '' : JSON.stringify(body, null, 2),
});

/** Guarda el id del recurso creado en una variable de la colección. */
const saveId = (variable) => [`const id = pm.response.json().data?.id;`, `pm.test('Devuelve el id creado', () => pm.expect(id).to.match(/^[a-f0-9]{24}$/));`, `pm.collectionVariables.set('${variable}', id);`];

/** El cuerpo de error sigue el formato común { message, code, errors? }. */
const errorCode = (code) => [`pm.test('Código de error ${code}', () => pm.expect(pm.response.json().code).to.eql('${code}'));`];

/** La descarga es del tipo esperado y no está vacía. */
const download = (type) => [
  `pm.test('Archivo ${type}', () => pm.expect(pm.response.headers.get('Content-Type')).to.include('${type}'));`,
  `pm.test('Archivo no vacío', () => pm.expect(pm.response.responseSize).to.be.above(500));`,
];

/** Respuesta paginada: data[] + meta { page, per_page, total, last_page }. */
const paginated = [
  `const body = pm.response.json();`,
  `pm.test('Lista paginada', () => { pm.expect(body.data).to.be.an('array'); pm.expect(body.meta).to.include.keys('page', 'per_page', 'total', 'last_page'); });`,
];

/**
 * Petición de la colección.
 * @param status código HTTP esperado (se valida en la pestaña Tests)
 * @param tests  líneas extra de la pestaña Tests
 * @param pre    líneas del Pre-request Script
 */
const req = ({ name, method, path, body, query, description, status = 200, tests = [], pre, noAuth, examples = [], headers = [], formdata }) => ({
  name,
  event: [
    ...(pre ? [script('prerequest', pre)] : []),
    script('test', [`pm.test('Status ${status}', () => pm.response.to.have.status(${status}));`, ...tests]),
  ],
  request: {
    method,
    description,
    auth: noAuth ? { type: 'noauth' } : undefined,
    header: [{ key: 'Accept', value: 'application/json' }, ...headers],
    body: formdata ? { mode: 'formdata', formdata } : body ? json(body) : undefined,
    url: url(path, query),
  },
  response: examples,
});

const listQuery = (sorts) => [
  q('search', '', 'Texto libre (código, nombre…)', true),
  q('page', 1, 'Página'),
  q('per_page', 10, '5, 10, 25, 50 o 100'),
  q('sort', 'created_at', sorts),
  q('direction', 'desc', 'asc | desc'),
];

const validation = (field, message) => ({ message: 'Los datos enviados no son válidos.', code: 'VALIDATION_ERROR', errors: { [field]: [message] } });
const product = { id: '6b0733db25a78435670588c1', code: 'PRD-000001', name: 'Casco de seguridad clase E', brand: '3M', price: 289.9, created_at: '2026-11-25T19:15:39+00:00', updated_at: '2026-11-25T19:15:39+00:00' };
const meta = { page: 1, per_page: 10, total: 36, last_page: 4 };
const sessionExample = {
  data: {
    status: 'authenticated', access_token: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9...', token_type: 'Bearer', expires_in: 600,
    user: { id: '6b0733db25a78435670588b6', code: 'USR-000001', email: 'admin@tap.test', name: 'Administrador TAP', must_change_password: false, two_factor_enabled: false, permissions: { products: ['view', 'create', 'update', 'delete', 'export'] } },
  },
};
const saveToken = "const d = pm.response.json().data; if (d?.access_token) pm.collectionVariables.set('accessToken', d.access_token);";

const collection = {
  info: {
    name: 'TAP · API Prueba técnica (Laravel 11 + MongoDB)',
    description:
      'Colección de la API v1.\n\n' +
      '1. Elige el entorno **TAP · Local (Docker)** y escribe `password` (está en `.env`: SEED_ADMIN_PASSWORD, o con `make credentials`).\n' +
      '2. Ejecuta **Sesión → Login**: guarda `accessToken` automáticamente.\n' +
      '3. Todas las demás peticiones usan `Bearer {{accessToken}}`. Cada una trae su prueba (pestaña *Tests*).\n' +
      '4. Para correr todo de una vez sin Postman: `make test-api` (Newman en Docker).\n\n' +
      'Las altas con foto usan `docs/postman/foto-ejemplo.png`: en Postman de escritorio, si no la encuentra, selecciónala en el campo `photo`.\n\n' +
      'Errores: `{ message, code, errors?, request_id }`. Documentación interactiva (Swagger): http://localhost:8080/docs/',
    schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
  },
  auth: { type: 'bearer', bearer: [{ key: 'token', value: '{{accessToken}}', type: 'string' }] },
  variable: [
    { key: 'baseUrl', value: 'http://localhost:8080/api/v1' },
    { key: 'email', value: 'admin@tap.test' },
    { key: 'password', value: '' },
    { key: 'accessToken', value: '' },
    { key: 'mfaToken', value: '' },
    { key: 'productId', value: '' },
    { key: 'userId', value: '' },
    { key: 'userEmail', value: '' },
    { key: 'profileId', value: '' },
    { key: 'profileName', value: '' },
    { key: 'assignProfileId', value: '' },
    { key: 'auditId', value: '' },
    { key: 'mailboxPassword', value: '' },
  ],
  item: [
    {
      name: 'Sistema',
      item: [
        req({
          name: 'Health check',
          method: 'GET',
          path: '/health',
          noAuth: true,
          tests: ["pm.test('MongoDB y Redis arriba', () => pm.expect(pm.response.json().checks).to.eql({ mongo: true, redis: true }));"],
          examples: [example('OK', 200, { status: 'ok', checks: { mongo: true, redis: true } })],
        }),
      ],
    },
    {
      name: 'Sesión',
      description: 'Inicio de sesión y datos de la sesión. Es lo primero que se ejecuta.',
      item: [
        req({
          name: 'Login',
          method: 'POST',
          path: '/auth/login',
          noAuth: true,
          body: { email: '{{email}}', password: '{{password}}' },
          description: 'Define la variable `password` (ver .env: SEED_ADMIN_PASSWORD). Si el usuario tiene 2FA activo, guarda `mfaToken` y hay que seguir con "Autenticación → 2FA — completar login".',
          tests: [
            'const d = pm.response.json().data;',
            "pm.test('Sesión iniciada o 2FA requerido', () => pm.expect(d.status).to.be.oneOf(['authenticated', 'two_factor_required']));",
            "if (d.status === 'authenticated') pm.collectionVariables.set('accessToken', d.access_token);",
            "if (d.status === 'two_factor_required') pm.collectionVariables.set('mfaToken', d.mfa_token);",
            "pm.test('El refresh token va en cookie HttpOnly, nunca en el cuerpo', () => pm.expect(JSON.stringify(d)).to.not.include('refresh'));",
          ],
          examples: [
            example('Sesión iniciada', 200, sessionExample),
            example('Requiere 2FA', 200, { data: { status: 'two_factor_required', mfa_token: 'eyJ...', expires_in: 300 } }),
            example('Credenciales inválidas', 401, { message: 'Correo o contraseña incorrectos.', code: 'INVALID_CREDENTIALS' }),
            example('Cuenta bloqueada (5 intentos)', 401, { message: 'Cuenta bloqueada temporalmente por intentos fallidos.', code: 'ACCOUNT_LOCKED', locked_until: '2026-11-25T20:15:00+00:00' }),
          ],
        }),
        req({
          name: 'Login — intento de inyección NoSQL (debe dar 400)',
          method: 'POST',
          path: '/auth/login',
          noAuth: true,
          body: { email: { $ne: null }, password: { $gt: '' } },
          description: 'Operadores de MongoDB ($ne, $gt…) en el cuerpo: el middleware RejectNoSqlOperators los rechaza antes de llegar al controlador.',
          status: 400,
          tests: errorCode('MALFORMED_INPUT'),
          examples: [example('Bloqueado', 400, { message: 'La solicitud contiene caracteres o estructuras no permitidas.', code: 'MALFORMED_INPUT' })],
        }),
        req({
          name: 'Refresh (usa cookie tap_rt)',
          method: 'POST',
          path: '/auth/refresh',
          noAuth: true,
          headers: [{ key: 'X-Requested-With', value: 'XMLHttpRequest' }],
          description: 'Postman conserva la cookie HttpOnly del login. Rota el refresh token (el anterior queda inválido).',
          tests: [saveToken],
          examples: [example('Renovado', 200, sessionExample), example('Sin cookie o vencida', 401, { message: 'La sesión expiró, vuelve a iniciar sesión.', code: 'INVALID_REFRESH_TOKEN' })],
        }),
        req({
          name: 'Yo (me)',
          method: 'GET',
          path: '/auth/me',
          tests: ["pm.test('Incluye permisos por sección', () => pm.expect(pm.response.json().data.permissions).to.be.an('object'));"],
        }),
      ],
    },
    {
      name: 'Autenticación (otras acciones)',
      description: 'Acciones manuales: cambian la cuenta (contraseña, 2FA) o cierran la sesión, por eso no corren en `make test-api`.',
      item: [
        req({
          name: '2FA — completar login',
          method: 'POST',
          path: '/auth/two-factor/challenge',
          noAuth: true,
          body: { mfa_token: '{{mfaToken}}', code: '123456' },
          description: 'Cambia `code` por el de tu app autenticadora (o un código de respaldo).',
          tests: [saveToken],
          examples: [example('Sesión iniciada', 200, sessionExample), example('Código inválido o ya usado', 422, validation('code', 'El código no es válido.'))],
        }),
        req({
          name: 'Recuperar contraseña',
          method: 'POST',
          path: '/auth/forgot-password',
          noAuth: true,
          body: { email: 'almacen@tap.test' },
          description: 'Valida que el usuario exista y envía una contraseña temporal al correo registrado (ver la bandeja en /mail/). La contraseña actual sigue funcionando hasta que se use la temporal.',
          examples: [
            example('Enviado', 200, { message: 'Enviamos una contraseña temporal a tu correo registrado.', data: { expires_at: '2026-11-25T21:00:00+00:00' } }),
            example('No existe', 404, { message: 'No existe un usuario registrado con ese correo.', code: 'USER_NOT_FOUND' }),
          ],
        }),
        req({ name: 'Cambiar contraseña', method: 'PUT', path: '/auth/password', body: { current_password: '{{password}}', password: 'NuevaClave#2026', password_confirmation: 'NuevaClave#2026' }, tests: [saveToken] }),
        req({ name: 'Logout', method: 'POST', path: '/auth/logout', status: 204, tests: ["pm.collectionVariables.set('accessToken', '');"] }),
      ],
    },
    {
      name: '2FA (cuenta propia)',
      description: 'Activar la verificación en dos pasos (TOTP). Requiere una app autenticadora para obtener los códigos.',
      item: [
        req({ name: 'Estado', method: 'GET', path: '/auth/two-factor' }),
        req({ name: 'Generar QR', method: 'POST', path: '/auth/two-factor/setup' }),
        req({ name: 'Confirmar y activar', method: 'POST', path: '/auth/two-factor/confirm', body: { code: '123456' } }),
        req({ name: 'Regenerar códigos de respaldo', method: 'POST', path: '/auth/two-factor/recovery-codes', body: { password: '{{password}}' } }),
        req({ name: 'Desactivar', method: 'DELETE', path: '/auth/two-factor', body: { password: '{{password}}', code: '123456' } }),
      ],
    },
    {
      name: 'Inicio',
      item: [
        req({
          name: 'Dashboard',
          method: 'GET',
          path: '/dashboard',
          tests: ["pm.test('Trae datos del tablero', () => pm.expect(pm.response.json().data).to.be.an('object'));"],
        }),
      ],
    },
    {
      name: 'Bandeja de correos (demo)',
      description: 'Acceso a Mailpit (/mail/), donde llegan los correos de alta y recuperación. Nginx lo protege con la cookie tap_mail.',
      item: [
        req({
          name: 'Acceso con credenciales',
          method: 'POST',
          path: '/mailbox/session',
          noAuth: true,
          status: 204,
          body: { user: 'revisor', password: '{{mailboxPassword}}' },
          description: 'Entrega la cookie tap_mail (HttpOnly, Path=/mail/). Luego abre http://localhost:8080/mail/ en el navegador. `mailboxPassword` = MAILPIT_UI_PASSWORD del .env.',
          tests: ["pm.test('Entrega la cookie de la bandeja', () => pm.expect(pm.response.headers.get('Set-Cookie') || '').to.include('tap_mail='));"],
          examples: [example('Acceso concedido', 204), example('Credenciales incorrectas', 401, { message: 'Usuario o contraseña incorrectos.', code: 'INVALID_CREDENTIALS' })],
        }),
        req({ name: 'Acceso con la sesión actual (users:create)', method: 'POST', path: '/mailbox/session/me', status: 204 }),
        req({ name: 'Cerrar acceso', method: 'DELETE', path: '/mailbox/session', noAuth: true, status: 204 }),
      ],
    },
    {
      name: 'Productos',
      item: [
        req({
          name: 'Listar',
          method: 'GET',
          path: '/products',
          query: listQuery('code | name | brand | price | created_at'),
          tests: paginated,
          examples: [example('Página', 200, { data: [product], meta })],
        }),
        req({
          name: 'Listar sin token (debe dar 401)',
          method: 'GET',
          path: '/products',
          noAuth: true,
          status: 401,
          tests: errorCode('UNAUTHENTICATED'),
          examples: [example('Sin sesión', 401, { message: 'Debes iniciar sesión.', code: 'UNAUTHENTICATED' })],
        }),
        req({
          name: 'Buscar con operador NoSQL (debe dar 400)',
          method: 'GET',
          path: '/products',
          query: [q('search[$ne]', 'x', 'Intento de inyección: el middleware lo rechaza')],
          status: 400,
          tests: errorCode('MALFORMED_INPUT'),
        }),
        req({
          name: 'Crear',
          method: 'POST',
          path: '/products',
          body: { name: 'Casco de seguridad clase E', brand: '3M', price: 289.9 },
          status: 201,
          tests: [...saveId('productId'), "pm.test('Código consecutivo PRD-', () => pm.expect(pm.response.json().data.code).to.match(/^PRD-\\d{6}$/));"],
          examples: [example('Creado', 201, { data: product })],
        }),
        req({
          name: 'Crear — precio con 4 dígitos (debe dar 422)',
          method: 'POST',
          path: '/products',
          body: { name: 'Producto inválido', brand: 'Marca', price: 1000 },
          description: 'Regla del examen: el precio admite máximo 3 dígitos enteros.',
          status: 422,
          tests: [...errorCode('VALIDATION_ERROR'), "pm.test('Error en price', () => pm.expect(pm.response.json().errors).to.have.property('price'));"],
          examples: [example('Precio con 4 dígitos', 422, validation('price', 'El precio admite máximo 3 dígitos enteros y 2 decimales (ej. 999.99).'))],
        }),
        req({ name: 'Ver', method: 'GET', path: '/products/{{productId}}', examples: [example('OK', 200, { data: product })] }),
        req({
          name: 'Editar',
          method: 'PUT',
          path: '/products/{{productId}}',
          body: { name: 'Casco clase E (editado)', brand: '3M', price: 299.5 },
          tests: ["pm.test('Precio actualizado', () => pm.expect(pm.response.json().data.price).to.eql(299.5));"],
        }),
        req({
          name: 'Exportar PDF',
          method: 'GET',
          path: '/products/export',
          query: [q('format', 'pdf', 'pdf | xlsx'), q('search', '', 'Opcional', true)],
          description: 'En Postman usa "Send and Download".',
          tests: download('application/pdf'),
        }),
        req({
          name: 'Exportar Excel',
          method: 'GET',
          path: '/products/export',
          query: [q('format', 'xlsx', 'pdf | xlsx')],
          tests: download('spreadsheetml'),
        }),
        req({ name: 'Eliminar', method: 'DELETE', path: '/products/{{productId}}', status: 204 }),
        req({ name: 'Ver eliminado (debe dar 404)', method: 'GET', path: '/products/{{productId}}', status: 404 }),
      ],
    },
    {
      name: 'Perfiles',
      item: [
        req({
          name: 'Catálogo de secciones',
          method: 'GET',
          path: '/sections',
          tests: ["pm.test('Incluye las 4 secciones', () => pm.expect(pm.response.json().data.map((s) => s.key)).to.include.members(['products', 'users', 'profiles', 'audit']));"],
        }),
        req({ name: 'Listar', method: 'GET', path: '/profiles', query: listQuery('code | name | created_at'), tests: paginated }),
        req({ name: 'Opciones (select)', method: 'GET', path: '/profiles/options' }),
        req({
          name: 'Crear',
          method: 'POST',
          path: '/profiles',
          pre: ["pm.collectionVariables.set('profileName', 'Auditor ' + Date.now());"],
          body: { name: '{{profileName}}', description: 'Solo lectura', sections: [{ section: 'products', actions: ['view', 'export'] }, { section: 'audit', actions: ['view'] }] },
          status: 201,
          tests: saveId('profileId'),
        }),
        req({
          name: 'Crear — nombre duplicado (debe dar 409)',
          method: 'POST',
          path: '/profiles',
          body: { name: '{{profileName}}', description: null, sections: [{ section: 'audit', actions: ['view'] }] },
          description: 'El nombre del perfil es único (índice único en MongoDB + regla de dominio). Un duplicado es un conflicto: 409.',
          status: 409,
          tests: errorCode('PROFILE_NAME_TAKEN'),
          examples: [example('Nombre en uso', 409, { message: 'Ya existe un perfil con ese nombre.', code: 'PROFILE_NAME_TAKEN', field: 'name' })],
        }),
        req({
          name: 'Detalle (modal)',
          method: 'GET',
          path: '/profiles/{{profileId}}',
          tests: ["pm.test('Muestra las secciones del perfil', () => pm.expect(pm.response.json().data.sections).to.have.lengthOf(2));"],
        }),
        req({ name: 'Editar', method: 'PUT', path: '/profiles/{{profileId}}', body: { name: '{{profileName}} editado', description: null, sections: [{ section: 'audit', actions: ['view', 'export'] }] } }),
        req({ name: 'Exportar PDF', method: 'GET', path: '/profiles/export', query: [q('format', 'pdf', 'pdf | xlsx')], tests: download('application/pdf') }),
        req({
          name: 'Eliminar',
          method: 'DELETE',
          path: '/profiles/{{profileId}}',
          status: 204,
          examples: [example('En uso', 409, { message: 'No se puede eliminar: el perfil está asignado a 1 usuario(s). Reasígnalos primero.', code: 'PROFILE_IN_USE', users_count: 1 })],
        }),
      ],
    },
    {
      name: 'Usuarios',
      item: [
        req({
          name: 'Perfiles disponibles (para asignar)',
          method: 'GET',
          path: '/profiles/options',
          tests: ["const first = pm.response.json().data?.[0]?.id;", "pm.test('Hay perfiles para asignar', () => pm.expect(first).to.be.a('string'));", "pm.collectionVariables.set('assignProfileId', first);"],
        }),
        req({ name: 'Listar', method: 'GET', path: '/users', query: listQuery('code | email | name | created_at'), tests: paginated }),
        req({
          name: 'Crear (multipart con foto)',
          method: 'POST',
          path: '/users',
          description: 'Foto obligatoria (jpg, png o webp, mín. 64x64). La contraseña se genera, se guarda con Argon2id y se envía al correo (ver /mail/). El correo es único por corrida para poder repetir la prueba.',
          pre: ["pm.collectionVariables.set('userEmail', 'laura.' + Date.now() + '@tap.test');"],
          formdata: [
            { key: 'name', value: 'Laura Pérez', type: 'text' },
            { key: 'email', value: '{{userEmail}}', type: 'text' },
            { key: 'phone', value: '+52 314 123 4567', type: 'text', description: 'Opcional; con código de país' },
            { key: 'photo', type: 'file', src: PHOTO },
            { key: 'profile_ids[]', value: '{{assignProfileId}}', type: 'text' },
          ],
          status: 201,
          tests: [
            ...saveId('userId'),
            'const u = pm.response.json().data;',
            "pm.test('Nunca devuelve la contraseña', () => pm.expect(JSON.stringify(u)).to.not.match(/password_hash|argon/i));",
            "pm.test('Tiene foto y perfil asignado', () => { pm.expect(u.photo_url).to.be.a('string'); pm.expect(u.profiles).to.have.lengthOf(1); });",
          ],
        }),
        req({
          name: 'Crear sin foto (debe dar 422)',
          method: 'POST',
          path: '/users',
          formdata: [
            { key: 'name', value: 'Sin Foto', type: 'text' },
            { key: 'email', value: 'sin.foto@tap.test', type: 'text' },
          ],
          status: 422,
          tests: ["pm.test('Error en photo', () => pm.expect(pm.response.json().errors).to.have.property('photo'));"],
          examples: [example('Sin foto', 422, validation('photo', 'La foto de perfil es obligatoria.'))],
        }),
        req({
          name: 'Crear con correo duplicado (debe dar 409)',
          method: 'POST',
          path: '/users',
          description: 'El correo (usuario) es único: índice único en MongoDB + regla de dominio. Un duplicado es un conflicto: 409.',
          formdata: [
            { key: 'name', value: 'Laura Duplicada', type: 'text' },
            { key: 'email', value: '{{userEmail}}', type: 'text' },
            { key: 'photo', type: 'file', src: PHOTO },
          ],
          status: 409,
          tests: [...errorCode('EMAIL_TAKEN'), "pm.test('Indica el campo email', () => pm.expect(pm.response.json().field).to.eql('email'));"],
          examples: [example('Correo en uso', 409, { message: 'Ya existe un usuario con ese correo.', code: 'EMAIL_TAKEN', field: 'email' })],
        }),
        req({
          name: 'Crear con teléfono sin código de país (debe dar 422)',
          method: 'POST',
          path: '/users',
          formdata: [
            { key: 'name', value: 'Teléfono Inválido', type: 'text' },
            { key: 'email', value: 'telefono.invalido@tap.test', type: 'text' },
            { key: 'phone', value: '3141234567', type: 'text' },
            { key: 'photo', type: 'file', src: PHOTO },
          ],
          status: 422,
          tests: ["pm.test('Error en phone', () => pm.expect(pm.response.json().errors).to.have.property('phone'));"],
        }),
        req({
          name: 'Detalle (con perfiles)',
          method: 'GET',
          path: '/users/{{userId}}',
          tests: ["pm.test('Incluye sus perfiles', () => pm.expect(pm.response.json().data.profiles).to.be.an('array').that.is.not.empty);"],
        }),
        req({ name: 'Editar (JSON)', method: 'PUT', path: '/users/{{userId}}', body: { name: 'Laura Pérez G.', email: '{{userEmail}}', phone: '+523141234567', profile_ids: ['{{assignProfileId}}'] } }),
        req({
          name: 'Editar con foto (multipart + _method=PUT)',
          method: 'POST',
          path: '/users/{{userId}}',
          formdata: [
            { key: '_method', value: 'PUT', type: 'text' },
            { key: 'name', value: 'Laura Pérez García', type: 'text' },
            { key: 'email', value: '{{userEmail}}', type: 'text' },
            { key: 'photo', type: 'file', src: PHOTO },
            { key: 'profile_ids[]', value: '{{assignProfileId}}', type: 'text' },
          ],
        }),
        req({ name: 'Exportar Excel', method: 'GET', path: '/users/export', query: [q('format', 'xlsx', 'pdf | xlsx')], tests: download('spreadsheetml') }),
        req({ name: 'Eliminar', method: 'DELETE', path: '/users/{{userId}}', status: 204 }),
      ],
    },
    {
      name: 'Bitácora',
      item: [
        req({
          name: 'Listar',
          method: 'GET',
          path: '/audit-logs',
          query: [q('entity_type', 'product', 'product | user | profile | auth'), q('event', 'updated', 'created | updated | deleted | login…'), q('page', 1, '')],
          tests: [...paginated, "const id = body.data?.[0]?.id;", "pm.test('Registró la edición del producto', () => pm.expect(id).to.be.a('string'));", "pm.collectionVariables.set('auditId', id);"],
        }),
        req({
          name: 'Detalle (antes vs después)',
          method: 'GET',
          path: '/audit-logs/{{auditId}}',
          tests: [
            'const d = pm.response.json().data;',
            "pm.test('Guarda la información anterior y la actual', () => { pm.expect(d).to.have.property('before'); pm.expect(d).to.have.property('after'); });",
          ],
        }),
        req({ name: 'Catálogo de filtros', method: 'GET', path: '/audit-logs/catalog' }),
        req({ name: 'Exportar PDF', method: 'GET', path: '/audit-logs/export', query: [q('format', 'pdf', 'pdf | xlsx')], tests: download('application/pdf') }),
      ],
    },
  ],
};

const environment = {
  name: 'TAP · Local (Docker)',
  values: [
    { key: 'baseUrl', value: 'http://localhost:8080/api/v1', enabled: true },
    { key: 'email', value: 'admin@tap.test', enabled: true },
    { key: 'password', value: '', type: 'secret', enabled: true },
    { key: 'mailboxPassword', value: '', type: 'secret', enabled: true },
  ],
  _postman_variable_scope: 'environment',
};

writeFileSync(join(here, 'TAP-Prueba.postman_collection.json'), JSON.stringify(collection, null, 2) + '\n');
writeFileSync(join(here, 'TAP-Local.postman_environment.json'), JSON.stringify(environment, null, 2) + '\n');
console.log('Colección y entorno de Postman generados en docs/postman/');
