# 🏠 Revisa Mi Vivienda

**Plataforma ciudadana y open source para registrar, visibilizar y facilitar la revisión inicial de inmuebles afectados por desastres.**

🌐 Proyecto desplegado: https://revisamivivienda.co

> **Estado del proyecto:** versión pública estable.
> Este repositorio se publica como código abierto para que pueda ser reutilizado, adaptado o continuado por terceros. Actualmente no cuenta con mantenimiento activo ni soporte técnico por parte del autor original.

---

## Sobre el proyecto

**Revisa Mi Vivienda** nació en Dosquebradas, Risaralda, Colombia, después del sismo de agosto de 2026 y de las afectaciones que dejó en diferentes ciudades y municipios.

La idea surgió a partir de una necesidad concreta: muchas viviendas presentan daños visibles y no siempre logran recibir atención inmediata, mientras ingenieros civiles, arquitectos y otros profesionales ofrecen voluntariamente su tiempo para apoyar procesos de revisión inicial.

El propósito de la plataforma es ayudar a:

* Registrar inmuebles afectados.
* Centralizar información.
* Dar visibilidad a casos pendientes.
* Priorizar necesidades.
* Facilitar el contacto entre ciudadanos y profesionales voluntarios.
* Mantener trazabilidad sobre el estado de cada caso.

---

## ⚠️ Importante

**Revisa Mi Vivienda no reemplaza, sustituye ni pretende cubrir las inspecciones, evaluaciones o revisiones oficiales realizadas por alcaldías, organismos de Gestión del Riesgo, entidades públicas, organismos de emergencia u otras autoridades competentes.**

La plataforma:

* No certifica que una vivienda sea segura.
* No emite certificados de habitabilidad.
* No reemplaza una evaluación estructural oficial.
* No sustituye los canales de emergencia.
* No garantiza que un caso sea atendido por un profesional.
* No debe utilizarse como mecanismo de respuesta ante una emergencia inmediata.

Las evaluaciones registradas en la plataforma deben entenderse únicamente como **observaciones o diagnósticos iniciales de carácter colaborativo**.

Ante un riesgo inmediato de colapso, desprendimientos, incendio, fuga de gas, personas heridas u otra situación de emergencia, se deben utilizar los canales oficiales correspondientes.

---

## ¿Cómo funciona?

### Para ciudadanos

Una persona puede:

1. Seleccionar departamento y municipio.
2. Registrar barrio, sector y dirección.
3. Describir los daños observados.
4. Indicar una prioridad percibida.
5. Responder preguntas básicas sobre señales visibles.
6. Cargar evidencias fotográficas.
7. Registrar información de contacto.
8. Publicar el reporte.

Los datos sensibles, como dirección exacta, teléfono y correo electrónico, no se muestran públicamente.

---

### Para revisores

Ingenieros civiles, arquitectos u otros profesionales pueden:

1. Crear una cuenta.
2. Registrar su profesión y datos profesionales.
3. Consultar inmuebles pendientes.
4. Filtrar casos por ubicación y prioridad.
5. Tomar un caso disponible.
6. Contactar al ciudadano.
7. Registrar el progreso de la revisión.
8. Publicar una evaluación o diagnóstico inicial.

---

## Prioridades

Los reportes pueden clasificarse como:

* 🔴 **Urgente**
* 🟠 **Alta**
* 🟡 **Media**
* 🟢 **Baja**

La prioridad representa una **necesidad de atención**, no un diagnóstico técnico ni una certificación estructural.

---

## Estados de un caso

Un reporte puede pasar por estados como:

```text
Pendiente
↓
Asignado
↓
Contactado
↓
Visita programada
↓
En revisión
↓
Revisado
```

También existen estados adicionales:

```text
Requiere segunda opinión
Derivado a una autoridad
Cerrado
```

---

## Privacidad

El proyecto fue diseñado siguiendo un principio básico de separación entre información pública y privada.

### Información pública

Puede incluir:

* Departamento.
* Municipio.
* Barrio o sector.
* Descripción del daño.
* Fotografías autorizadas.
* Prioridad.
* Estado.
* Fecha del reporte.
* Evaluación publicada.
* Nombre del revisor cuando corresponda.

### Información restringida

No se publica:

* Dirección exacta.
* Teléfono.
* Correo electrónico.
* Información privada de contacto.

Estos datos deben estar disponibles únicamente para usuarios autorizados cuando sean necesarios para gestionar un caso.

El proyecto fue concebido teniendo en cuenta la normativa colombiana sobre protección de datos personales, incluida la **Ley 1581 de 2012**.

---

## Tecnologías

### Backend

* PHP 8+
* MySQL / MariaDB
* PDO

### Frontend

* HTML5
* CSS3
* Bootstrap 5
* Vanilla JavaScript
* Fetch API / AJAX

### Infraestructura

La implementación original fue construida para funcionar en hosting PHP convencional con MySQL.

Las evidencias fotográficas pueden almacenarse inicialmente en filesystem local y posteriormente migrarse a servicios como:

* Cloudflare R2
* Amazon S3
* DigitalOcean Spaces

---

## Estructura del proyecto

```text
/
├── app/
├── assets/
├── config/
│   └── config.example.php
├── scripts/
├── sql/
│   ├── schema.sql
│   └── seed_locations_initial.sql
├── uploads/
│
├── index.php
├── report.php
├── case.php
├── reviewer.php
├── reviewer-register.php
├── review-case.php
├── take-case.php
├── login.php
├── logout.php
├── admin.php
└── api-municipalities.php
```

---

## Instalación

### 1. Clonar el repositorio

```bash
git clone <URL-DEL-REPOSITORIO>
cd revisamivivienda
```

### 2. Crear el archivo de configuración

Copiar:

```bash
cp config/config.example.php config/config.php
```

y configurar las credenciales de MySQL.

### 3. Crear las tablas

Ejecutar:

```text
sql/schema.sql
```

dentro de una base de datos MySQL existente.

### 4. Cargar ubicaciones iniciales

Ejecutar:

```text
sql/seed_locations_initial.sql
```

Los municipios habilitados para recibir reportes pueden modificarse desde la base de datos o desde la administración de la aplicación.

### 5. Configurar permisos

El directorio de evidencias debe tener permisos de escritura para PHP:

```text
/uploads/
```

### 6. Usar HTTPS

En producción se recomienda utilizar siempre HTTPS.

---

## Seguridad

La aplicación incluye o contempla:

* Prepared statements con PDO.
* Protección CSRF.
* Escape de contenido para prevenir XSS.
* Password hashing.
* Validación de roles.
* Validación MIME de fotografías.
* Restricciones de tamaño de archivos.
* Nombres aleatorios para evidencias.
* Separación entre datos públicos y privados.
* Historial de eventos.
* Restricciones de acceso administrativo.

Antes de utilizar este proyecto en un escenario de alta escala, institucional o misión crítica, se recomienda realizar una auditoría independiente de seguridad, privacidad y arquitectura.

---

## Más allá de los sismos

Aunque **Revisa Mi Vivienda** nació como respuesta a una emergencia sísmica en Colombia, su concepto puede adaptarse a otros tipos de eventos.

Por ejemplo:

* Inundaciones.
* Deslizamientos.
* Huracanes.
* Vendavales.
* Incendios.
* Daños derivados de otras emergencias o desastres.

El código se publica con la intención de que pueda servir como punto de partida para soluciones de mayor alcance.

---

## Uso y continuidad del proyecto

La versión publicada representa el alcance final desarrollado originalmente para esta iniciativa.

**No existe actualmente un compromiso de mantenimiento, soporte técnico, desarrollo de nuevas funcionalidades o revisión de Pull Requests por parte del autor original.**

Quien considere útil el proyecto puede:

* Hacer un fork.
* Modificarlo.
* Adaptarlo a otras ciudades o países.
* Mejorar su arquitectura.
* Ampliarlo para otros tipos de desastres.
* Integrarlo con plataformas institucionales.
* Convertirlo en una iniciativa de mayor escala.

La intención al liberar el código es permitir que la idea pueda continuar de forma independiente si otras personas, organizaciones o comunidades encuentran valor en ella.

---

## Origen

Este proyecto fue creado de manera independiente desde **Dosquebradas, Risaralda, Colombia**, como una iniciativa ciudadana para aportar desde el desarrollo de software durante una situación de emergencia.

No representa ni está afiliado oficialmente con ninguna alcaldía, organismo gubernamental, entidad de Gestión del Riesgo, organismo de emergencia o entidad pública.

---

## Tecnología con propósito

La idea parte de algo sencillo:

> **Usar lo que sabemos hacer para ayudar cuando nuestra comunidad lo necesita.**

Si este código puede servir para mejorar la iniciativa original o convertirse en la base de algo más grande, habrá cumplido una parte importante de su propósito.

---

## Licencia

Este proyecto se distribuye bajo la licencia **MIT**.

Puedes utilizarlo, modificarlo y distribuirlo de acuerdo con los términos establecidos en el archivo `LICENSE`.

---

## Proyecto

**Revisa Mi Vivienda**
https://revisamivivienda.co

Dosquebradas, Risaralda — Colombia 🇨🇴
