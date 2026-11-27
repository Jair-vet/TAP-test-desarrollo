# Hosting: dejarlo publicado sin tu computadora

> **Opcional — no se usa en esta entrega.** El sistema se entrega en local (http://localhost:8080), lo que el
> examen permite ("puede ser en un servidor local"). Esta guía y la carpeta `deploy/` quedan solo como
> referencia por si algún día se quiere publicar en un servidor propio.

Objetivo: que los evaluadores entren cuando quieran (sábado, lunes…) sin que tu Mac esté encendida.

## 1. Qué necesita el sistema

Medido con `docker stats`: **~485 MB de RAM en total** (MongoDB ~316 MB; API, Nginx, Redis, worker y
Mailpit < 70 MB cada uno). Compilar las imágenes (Angular + extensiones PHP) pide ~2 GB, por eso el
script agrega 2 GB de swap en máquinas chicas.

## 2. Opciones evaluadas (noviembre 2026)

| Opción | Costo | ¿Sirve tal cual (Docker Compose)? | Notas |
|---|---|---|---|
| **Oracle Cloud Always Free** (VM ARM) | **$0** permanente | ✅ Sí | Desde jun-2026 el límite gratuito es **2 CPU / 12 GB** (antes 4/24): sobra. Pide tarjeta para verificar identidad (no cobra). A veces "Out of capacity" en la región |
| **Hetzner CAX11** (ARM 2 vCPU / 4 GB) | ~€6/mes con tope; **cobro por hora** (~€0.01/h → un fin de semana ≈ €1) | ✅ Sí | El más rápido y estable de montar (~15 min). Borras el servidor al terminar y deja de cobrar |
| Google Cloud e2-micro | $0 | ❌ Muy justo | 1 GB de RAM: Mongo + compilación no caben bien |
| AWS | Créditos por 6 meses | ✅ | Ya no hay "12 meses gratis" para cuentas nuevas; cuando se acaban los créditos, cobra |
| Render (plan gratis) | $0 | ❌ Requiere rehacer | Un solo contenedor, se "duerme" tras 15 min (primera carga ~1 min), sin disco persistente |
| Railway / Fly.io | ~$5/mes | ⚠ Parcial | Hay que separar cada servicio; el costo por RAM puede superar lo gratuito |

**Recomendación:** si quieres **$0**, Oracle Cloud Always Free; si quieres que quede en **15 minutos y
sin sorpresas**, Hetzner por horas (≈ €1 el fin de semana). Las dos usan exactamente el mismo kit
(`deploy/`), así que puedes empezar con una y cambiarte a la otra.

### URL fija y HTTPS gratis: DuckDNS + Caddy
- **DuckDNS** te da un subdominio gratis (ej. `tap-prueba.duckdns.org`) que apunta a la IP del servidor.
  Está en la *Public Suffix List* (comprobado), así que Let's Encrypt emite su certificado sin los límites
  compartidos que rompen a `sslip.io`/`nip.io`.
- **Caddy** (contenedor extra en el servidor) obtiene y renueva el certificado solo.
- Resultado: `https://tap-prueba.duckdns.org` **fijo**, no cambia como el túnel rápido.

## 3. Paso a paso

### A) Crear el servidor

**Oracle Cloud (gratis) — elegido:**
1. **Cuenta** en cloud.oracle.com → *Sign up / Start for free*.
   - Pide una tarjeta **solo para verificar** (puede aparecer un cargo temporal de ~$1 que se reembolsa).
     Suelen rechazar tarjetas prepago o virtuales; usa una de crédito/débito normal con la misma dirección.
   - **Home region**: elige una cercana, p. ej. *Mexico Central (Querétaro)*, *Mexico Northeast
     (Monterrey)* o *US East (Ashburn)*. **No se puede cambiar después** y los recursos gratis solo
     existen en ella.
   - La activación puede tardar de minutos a unas horas (llega un correo).
2. **Crear la VM**: menú ☰ → *Compute → Instances → Create instance*:
   - *Name*: `tap-prueba`.
   - *Image and shape → Edit*:
     - *Image* → **Canonical Ubuntu 24.04** (la normal, no "Minimal").
     - *Shape* → *Ampere* → **VM.Standard.A1.Flex** con **2 OCPU y 12 GB** (debe decir *Always Free-eligible*).
   - *Networking*: deja la VCN que crea por defecto y **Assign a public IPv4 address = Sí**.
   - *Add SSH keys → Paste public keys*: pega tu llave de Jair-vet. Para copiarla:
     ```bash
     pbcopy < ~/.ssh/id_ed25519.pub
     ```
   - *Create*. En 1-2 min queda en verde (*Running*); copia la **Public IP address**. Usuario SSH: **`ubuntu`**.
   - Si sale **"Out of host capacity"**: no hay cupo ARM en ese momento. Reintenta en unas horas o cambia
     de *Availability Domain* (en *Placement*). Pasar la cuenta a *Pay As You Go* suele dar prioridad y
     sigue siendo $0 mientras no salgas de lo gratuito (sí pide confirmar la tarjeta).
3. **Abrir 80 y 443**: en la instancia → *Primary VNIC → Subnet → Security Lists → Default Security
   List → Add Ingress Rules*: *Source CIDR* `0.0.0.0/0`, *IP Protocol* TCP, *Destination Port Range*
   `80,443` → *Add*. (El firewall interno de Ubuntu lo abre `deploy.sh`.)
4. Nota: Oracle puede reclamar VMs gratuitas **ociosas por 7 días** (CPU muy baja). Para una evaluación de
   unos días no afecta.

**Hetzner (por horas):**
1. Cuenta en accounts.hetzner.com (puede pedir verificar identidad) y método de pago.
2. Cloud Console → *Add Server*: ubicación (Ashburn/Hillsboro o Europa), **Ubuntu 24.04**,
   tipo **Arm64 · CAX11** (o x86 CX23), agrega tu llave SSH. Usuario SSH: **`root`**.
   El firewall de Hetzner viene abierto por defecto.

### B) Subdominio gratis (DuckDNS, 2 minutos)
1. Entra a duckdns.org con tu cuenta de GitHub o Google.
2. Crea el subdominio (ej. `tap-prueba`) y en *current ip* pon la **IP pública** del servidor → *update ip*.

### C) Desplegar (desde tu Mac, un comando)
```bash
cd ~/Downloads/prueba-de-desarrollo
```
```bash
SSH_KEY=~/.ssh/id_ed25519 ./deploy/deploy.sh ubuntu@IP_DEL_SERVIDOR tap-prueba.duckdns.org
```
(En Hetzner: `root@IP…`. `SSH_KEY` es la llave privada cuya pública subiste a la VM.) El script:
1. Instala Docker/Compose desde los repositorios de Ubuntu, agrega swap y abre 80/443 (incluye la regla
   que traen las VMs de Oracle).
2. Copia el proyecto con `rsync` (sin `.env`, `node_modules`, `vendor` ni datos locales).
3. Crea el `.env` **del servidor**: secretos de infraestructura nuevos (APP_KEY, Mongo, Redis) y **las
   mismas contraseñas demo** que tienes en `~/Downloads/credenciales-prueba-tap.txt`.
4. Construye y levanta todo con `deploy/docker-compose.server.yml` (agrega Caddy con HTTPS).
5. Verifica `https://tu-dominio/api/v1/health`.

Para subir cambios después, vuelve a correr el mismo comando.

### D) Después de la evaluación
- **Hetzner:** borra el servidor en la consola → deja de cobrar.
- **Oracle:** puedes dejarlo (es gratis) o terminar la instancia.
- DuckDNS: borra el subdominio si ya no lo usas.

## 4. Archivos del kit

| Archivo | Qué hace |
|---|---|
| `deploy/deploy.sh` | Orquesta todo desde tu Mac (SSH + rsync) |
| `deploy/server-setup.sh` | Prepara Ubuntu: Docker, swap, firewall (idempotente) |
| `deploy/server-env.sh` | Genera/actualiza el `.env` del servidor |
| `deploy/docker-compose.server.yml` | Override: Caddy (80/443), sin puerto 8080, caché de Mongo acotada |
| `deploy/Caddyfile` | HTTPS automático para `PUBLIC_HOST` |

## 5. Alternativa sin abrir puertos
Si ya tienes un dominio en Cloudflare: túnel con nombre (ver `docs/DESPLIEGUE.md` §4, opción B) corriendo
en el servidor. No expone puertos y da URL fija con tu dominio.
