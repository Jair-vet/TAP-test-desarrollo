# Guion de la demostración (10–15 min)

## Antes de empezar
```bash
make up                     # levanta el sistema en http://localhost:8080
make credentials            # contraseñas demo y de /mail
make public                 # (opcional) URL HTTPS temporal; créala justo antes y revisa con make public-status
```
Ten abiertos: el sistema, `/docs/` (Swagger), `/mail/` (Mailpit; entra por `/correos` o con el menú "Correos (demo)") y Postman con la colección importada.

## 1. Arquitectura (2 min)
- Abre `docs/ARQUITECTURA.md` → diagrama hexagonal.
- Muestra `backend/src/Catalog/` (Domain / Application / Infrastructure) y
  `app/Providers/HexagonalServiceProvider.php`: "aquí decido que `ProductRepository` lo implementa Mongo;
  si me piden DynamoDB, escribo otro adaptador y cambio esta línea".

## 2. Recorrido funcional (5 min)
1. **Login como `consulta@tap.test`** → solo ve Productos y Usuarios, sin botones de editar/eliminar.
   Pega en la barra `/perfiles` → "Sin acceso" (guard) y en Swagger el 403 (servidor).
2. **Login como `admin@tap.test`** → Inicio con contadores y actividad.
3. **Productos**: busca "dielectricas" (sin acento) → encuentra "Botas dieléctricas"; ordena por precio;
   crea uno con precio `1234` → no permite más de 3 dígitos; edita; descarga PDF y Excel.
4. **Usuarios**: alta con foto (arrastrar), teléfono con código de país → abre `/mail/` y muestra el correo
   de bienvenida con la contraseña; detalle con perfiles.
5. **Perfiles**: crea uno marcando acciones (marcar "Eliminar" marca "Consultar" sola); intenta borrar un
   perfil asignado → 409.
6. **Bitácora**: abre la edición del producto → "información anterior vs actual" resaltada.

## 3. Seguridad (4 min)
1. **2FA**: Seguridad → Activar → escanear QR con el celular → códigos de respaldo → cerrar sesión →
   entrar pidiendo código.
2. **Recuperación**: "¿La olvidaste?" con un correo inexistente (valida existencia) y con uno real → correo
   con contraseña temporal → al entrar obliga a cambiarla.
3. **Inyección NoSQL** en Postman: "Sesión → Login — intento de inyección NoSQL" → 400 (y "Productos → Buscar con operador NoSQL" → 400).
4. DevTools → Application: no hay token en localStorage; la cookie `tap_rt` es HttpOnly.

## 4. Calidad (2 min)
- `make test` (o la pestaña Actions de GitHub): Pint PSR-12, Larastan nivel 6, 64 pruebas PHPUnit, ESLint,
  54 pruebas Jasmine, build y la colección de Postman con Newman (44 peticiones, 87 aserciones).
- `ProductServiceTest`: pruebas del caso de uso **sin base de datos** gracias a los puertos.

---

## Preguntas probables

**¿Por qué hexagonal y no Clean?** Misma regla de dependencias; hexagonal encaja con "3 BD NoSQL
posibles" y con Laravel (controladores = adaptadores de entrada). Ver ARQUITECTURA §1.

**¿Por qué MongoDB?** Corre local, driver oficial para Laravel, validación `$jsonSchema`, índices únicos,
TTL, `Decimal128`. La bitácora aprovecha el esquema flexible.

**¿Cómo evitas inyección si no hay SQL?** 4 capas: middleware anti-operadores, validación de tipos,
filtros tipados + regex escapada + lista blanca de orden, usuario de BD de mínimo privilegio.

**¿Dónde guardas el JWT y por qué?** Access token en memoria (10 min); refresh opaco en cookie
HttpOnly/SameSite=Strict con rotación y detección de reúso. Un XSS no se lleva la sesión.

**¿Cómo cierras sesión si el JWT es "stateless"?** Denylist del `jti` en Redis con TTL + `tokenVersion`
por usuario para revocación masiva.

**¿Cómo funciona la caché sin servir datos viejos?** Llaves con versión por namespace; cada escritura sube
la versión (invalidación O(1)). Además ETag/304 en HTTP.

**¿Qué pasa si se cae Redis?** La caché degrada a lectura directa de Mongo (no tumba la app).

**¿Por qué Laravel 11 si ya no tiene soporte?** Lo exige el examen; los 4 avisos abiertos están
mitigados (SEGURIDAD §8) y la migración a 12/13 queda acotada a infraestructura.

**¿Cómo agregarías una sección nueva?** GUIA-DEL-CODIGO §2.

**¿Precio "máximo 3 dígitos"?** Interpretado como ≤ 3 enteros + 2 decimales (0.01–999.99); está en un solo
lugar (`Price::PATTERN`) si se requiere solo enteros.
