# Revisa Mi Vivienda

**Revisa Mi Vivienda** es una plataforma web comunitaria y gratuita para registrar inmuebles afectados por eventos sísmicos, organizar casos pendientes de revisión y facilitar la colaboración de ingenieros civiles, arquitectos y otros profesionales voluntarios.

Sitio del proyecto: **https://revisamivivienda.co**

## Propósito

La plataforma busca dar visibilidad y trazabilidad a inmuebles afectados que aún necesitan una revisión inicial. Permite:

- registrar una vivienda o inmueble afectado;
- adjuntar evidencia fotográfica;
- clasificar prioridad y ubicación;
- filtrar reportes por departamento, municipio, prioridad y estado;
- registrar revisores;
- tomar casos disponibles;
- dejar una evaluación o diagnóstico inicial;
- mantener un historial público del proceso.

> **Importante:** este proyecto no reemplaza, sustituye ni pretende cubrir inspecciones, evaluaciones o revisiones oficiales realizadas por alcaldías, organismos de gestión del riesgo, entidades públicas u otras autoridades competentes. Tampoco emite certificaciones de seguridad, estabilidad o habitabilidad. Es una herramienta colaborativa y complementaria.

## Tecnología

- PHP 8.x
- MySQL / MariaDB
- HTML5
- CSS3
- Bootstrap 5
- JavaScript ES6
- PDO

No requiere un framework PHP.

## Estructura

```text
app/                 Lógica compartida, sesión, PDO, helpers y renderizado
config/              Configuración local/producción
scripts/             Utilidades CLI y tareas programadas
sql/                 Esquema y datos iniciales
uploads/             Evidencias cargadas (ignoradas por Git)
assets/              CSS y JavaScript
index.php             Homepage y listado público
report.php            Registro de afectaciones
case.php              Detalle público del reporte
reviewer.php          Panel del revisor
review-case.php       Gestión y evaluación del caso
admin.php             Administración básica
```

## Instalación local / servidor

### 1. Crear la base de datos

Crea una base MySQL/MariaDB y ejecuta:

```text
sql/schema.sql
```

Después puedes cargar ubicaciones iniciales con:

```text
sql/seed_locations_initial.sql
```

### 2. Configurar la aplicación

Copia:

```bash
cp config/config.example.php config/config.php
```

Edita `config/config.php` con tus datos reales:

```php
'db' => [
    'host' => 'localhost',
    'name' => 'nombre_base_datos',
    'user' => 'usuario_base_datos',
    'pass' => 'contraseña',
    'charset' => 'utf8mb4',
],
```

`config/config.php` está excluido mediante `.gitignore` y **nunca debe publicarse en GitHub**.

### 3. Permisos de uploads

El servidor PHP debe poder escribir en:

```text
uploads/
```

### 4. Crear administrador

Desde CLI:

```bash
php scripts/create-admin.php admin@ejemplo.com "UnaContraseñaSegura" "Administrador"
```

### 5. Cron de asignaciones

Para liberar asignaciones vencidas puedes ejecutar periódicamente:

```bash
php scripts/cron-release.php
```

En Hostinger puede configurarse mediante Cron Jobs en hPanel.

## Flujo principal

```text
Ciudadano
   ↓
Registra inmueble afectado
   ↓
Reporte pendiente
   ↓
Revisor registrado
   ↓
Toma el caso
   ↓
Contacto / visita / revisión
   ↓
Evaluación inicial
   ↓
Caso revisado
```

## Privacidad

La aplicación diferencia datos públicos y privados. Por diseño, datos como dirección exacta, teléfono y correo del reportante no deben mostrarse públicamente y se reservan para roles autorizados y el revisor asignado.

Las personas responsables de desplegar una instancia deben revisar y adaptar sus políticas de privacidad, tratamiento de datos, retención y consentimiento a la legislación aplicable.

## Seguridad

La aplicación incluye, entre otros:

- consultas preparadas con PDO;
- hashing de contraseñas;
- protección CSRF;
- sesiones y control de roles;
- validación de uploads;
- identificadores públicos no secuenciales;
- separación de datos públicos y privados;
- historial de eventos.

Antes de utilizarla a gran escala se recomienda realizar una revisión de seguridad independiente.

## Open source

El proyecto se publica para que pueda ser auditado, mejorado y adaptado. Aunque nació como respuesta a afectaciones por un sismo en Colombia, la arquitectura puede evolucionar para apoyar el registro y seguimiento de daños causados por otros tipos de desastres.

Las contribuciones son bienvenidas. Consulta [CONTRIBUTING.md](CONTRIBUTING.md).

## Licencia

MIT. Consulta [LICENSE](LICENSE).
