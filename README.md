# Sistema académico · IETE del Llano

Sistema de notas de la **Institución Educativa Técnica Empresarial del Llano** (Tauramena, Casanare).

Cubre el ciclo completo de las calificaciones del modelo semipresencial: la estructura académica del semestre, la carga de notas —por importación masiva o por el docente en su planilla— y la emisión de boletines y certificados de estudio en PDF.

El acceso es solo para personal de la institución. **No hay registro público**: las cuentas las crea un administrador.

---

## Stack

| | |
|---|---|
| **PHP** | 8.4 |
| **Framework** | Laravel 13 · Livewire 4 · Flux (gratuito) |
| **Auth** | Laravel Fortify (2FA disponible) + spatie/laravel-permission |
| **Front** | Vite 8 · Tailwind 4 · fuentes autoalojadas (Bunny) |
| **PDF** | barryvdh/laravel-dompdf |
| **Base de datos** | MySQL |
| **Calidad** | Pest 4 · Pint |
| **Node** | 22 |

---

## Puesta en marcha local

```bash
git clone https://github.com/KevinEscobarV/ietellano.git
cd ietellano
composer setup
```

`composer setup` instala dependencias de PHP y JS, crea el `.env` a partir del ejemplo, genera la `APP_KEY`, corre las migraciones y compila los assets.

Antes de correrlo, crea la base de datos y ajusta las credenciales en el `.env`:

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=ie_llano_db
DB_USERNAME=root
DB_PASSWORD=
```

Después, para levantar el entorno de desarrollo (servidor, cola y Vite en paralelo):

```bash
composer dev
```

Para tener datos con los que trabajar:

```bash
php artisan db:seed
```

> El seeder principal crea roles, permisos y un usuario `superadmin@example.com` con contraseña `password`. La estructura académica y los docentes salen de los archivos de `database/data/`, que **no viajan en el repositorio** (ver [Datos](#datos)).

---

## Roles y accesos

| Rol | Qué puede hacer |
|---|---|
| `super-admin` | Todo. Pasa cualquier verificación de permisos. |
| `admin` | Todo el panel excepto eliminar roles. |
| `docente` | Solo su portal: sus materias, las notas y la asistencia de esas materias. Ve sus datos personales pero no los edita ni puede borrar su cuenta; lo único suyo es la contraseña. |
| `editor` / `viewer` | Consulta del tablero y de usuarios. |

### Cómo entra un docente

Los docentes se importan desde Excel como registros de la tabla `teachers`, sin cuenta. Para darles acceso: **Docentes → Crear acceso**. Eso crea el usuario con el correo del docente, le asigna el rol `docente`, lo vincula a su registro y muestra **una sola vez** una contraseña temporal para entregársela.

La contraseña se genera en pantalla en lugar de enviarse por correo porque el hosting no tiene SMTP configurado (`MAIL_MAILER=log`). Por la misma razón esas cuentas se crean ya verificadas: las rutas usan el middleware `verified` y nadie podría confirmar un correo que no sale. Si algún día se configura SMTP real, vale la pena cambiarlo por un enlace de restablecimiento.

Qué materias ve cada docente lo decide el vínculo `teachers.user_id`, no el rol. La regla se aplica en tres capas: el middleware `teacher` exige que la cuenta esté vinculada, `CoursePolicy` verifica que el curso sea suyo antes de abrir la planilla, y al guardar se comprueba que el estudiante esté matriculado en ese curso.

### Consulta pública del estudiante

El estudiante no tiene cuenta. En **`/consulta`** escribe su documento y su apellido y ve su propio boletín: los semestres en los que está matriculado, las notas de los dos periodos, las fallas y el PDF para descargar. Los certificados no están ahí; se siguen tramitando en la institución.

El apellido no es un adorno. Un número de cédula circula con facilidad, así que es lo único que impide abrir el boletín ajeno. Se compara sin tildes ni mayúsculas y sirve cualquiera de los dos apellidos (`App\Services\StudentLookup`); ocho intentos fallidos desde la misma IP cierran la consulta cinco minutos.

De la consulta solo queda en sesión el `id` del estudiante verificado (`consulta.student_id`), y de ahí sale todo lo que se muestra. La URL del PDF dice qué semestre se quiere, nunca de quién: cambiarle el número no abre el de otra persona.

Los semestres abiertos se consultan igual que los cerrados, con un aviso de que las notas todavía pueden cambiar. Quien no tenga su documento actualizado en `students.document` no puede entrar —hoy son once: ocho con documento provisional (`sd00…`) y tres sin ninguno— y se corrigen desde **Estudiantes**.

---

## Modelo de datos

```
Cycle  ─┬─ Group ──── Enrollment ──── Student
        │                                 │
        ├─ Area ─── Subject               │
        │             │                   │
        └─ Course ────┘                   │
             │  (ciclo + materia + grupo + periodo + docente)
             │                            │
             ├────────── Grade ───────────┤
             │        (una nota por curso y estudiante)
             │                            │
             └─ Attendance ── ClassSession ┘
                (una marca por curso, día y estudiante)

Cycle ── EditWindow ── Course   (permiso con plazo para editar un ciclo ya cerrado)
```

- Un **ciclo** es un semestre de un nivel (`Ciclo 3B · 2026-2`). Puede apuntar al ciclo anterior para armar boletines de dos semestres.
- Un **curso** es la combinación materia + grupo + periodo. Un curso sin grupo cubre a todo el ciclo.
- Las **áreas** agrupan materias para que el boletín muestre una línea por área con sus componentes.
- Escala de **0 a 5**, se aprueba con **3.0**. Desempeño según el Decreto 1290: Superior ≥ 4.6 · Alto ≥ 4.0 · Básico ≥ 3.0 · Bajo por debajo.

### Asistencia

Una falla pertenece a una clase concreta: **curso + día**. Eso es lo que permite decir "perdió Matemáticas por inasistencia", que un registro por jornada no soportaría.

El **calendario** se arma primero, en *Estructura académica → Calendario*: día de la semana, primer día y último día, y el sistema genera una sesión por semana. Los festivos se quitan uno a uno después. Que exista la sesión significa que ese día hubo clase.

Luego cada **docente** marca desde su portal, en la materia y el día: presente, falló o justificada. Marcar a uno guarda la planilla entera — así queda registrado que la clase se dictó, y un día sin fallas no se confunde con un día en que nadie pasó lista.

De ahí salen tres cosas: la consulta por ciclo del panel (*Asistencia*, con exportación a CSV), la columna de fallas del boletín, y el aviso del tablero cuando hay cursos con asistencia pendiente.

> **Una clase solo cuenta como dictada si alguien pasó asistencia.** Un curso donde nunca se pasó lista no tiene denominador, así que no se le reprocha nada a ningún estudiante. El tope para perder la materia es `max_absence_rate` en `config/institution.php`, hoy en 20% de las clases dictadas, y solo cuentan las fallas **sin justificar**.

### Cierre de semestre

Un semestre termina cerrándose, en *Calificaciones → Cierre de semestre*. A partir de ahí el docente **sigue viendo** sus planillas de notas y de asistencia, pero ya no puede escribir en ellas. El administrador no pierde nada: el editor de notas del panel sigue funcionando y muestra un aviso de que ese semestre ya está cerrado.

Para una corrección puntual se abre una **ventana de edición**: un permiso con fecha y hora de vencimiento sobre un ciclo entero o sobre una sola materia. Mientras dura, ese docente vuelve a escribir; después se cierra sola. La ventana no se borra al vencerse — queda con quién la abrió y por qué, que es lo primero que se pregunta cuando una nota cambió después del cierre.

> La escritura la decide `CoursePolicy`: `view` es "es mi materia" y `grade`/`attend` son eso **más** que el semestre esté abierto. Quien resuelve lo segundo es `TermService`.

### Cómo se separan los semestres

**El ciclo es la unidad de semestre.** `Ciclo3AS12026` y `Ciclo3BS22026` son filas distintas, cada una con sus grupos, cursos, matrículas y notas; importar un semestre nuevo no toca lo anterior.

Lo único que se comparte entre semestres es el **estudiante**: es la misma persona, y se reconoce primero por correo y, si no aparece, por documento. El documento es el que manda cuando el correo cambia — en 2026-1 varios entraron con un `nullN@iellano.com` de relleno y este semestre ya traen el suyo.

Como el nivel se repite cada semestre, en pantalla los ciclos se muestran con su semestre (`Ciclo 5 · 2026-2`) y los selectores ordenan el semestre en curso primero.

Un ciclo de dos semestres —el 3 y el 4— enlaza el semestre 1 con el 2 por `previous_cycle_id`, y el boletín sale con columnas *Semestre 1 / Semestre 2 / Final*. El semestre que queda como anterior desaparece de la lista de boletines: el boletín del ciclo completo se genera desde el semestre 2.

---

## Datos

Los archivos fuente (`.csv` de matrícula, `.xlsx` de docentes y calificaciones) viven en `database/data/` y **están fuera del repositorio** por privacidad: contienen nombres y documentos de estudiantes reales. Van organizados por semestre: `database/data/2026-2/` es el semestre 2 de 2026.

```bash
# Estructura académica: ciclos, grupos, cursos, estudiantes y matrículas
php artisan db:seed --class=AcademicStructureSeeder

# Docentes y su asignación a los cursos (después del anterior: necesita los cursos creados)
php artisan db:seed --class=TeachersSeeder

# Notas (acepta una ruta como argumento)
php artisan grades:import
php artisan grades:import "database/data/2026-2/Calificaciones"

# Filas que no se pudieron cruzar con un estudiante matriculado
php artisan grades:unmatched

# Un semestre ya cerrado, como ciclo histórico ligado al actual
php artisan grades:import-previous archivo.xlsx --target=4B --level=4A --year=2025 --semester=2
```

Ambos seeders son idempotentes: se pueden volver a correr sin duplicar nada.

En las notas, el cruce entre la fila del Excel y el estudiante se hace primero por correo y, si no aparece, por documento. **El nombre del archivo `.xlsx` tiene que ser el código del curso** (`C3BMATM1S22026.xlsx`); si no coincide con ninguno, el archivo se salta. `grades:unmatched` es la herramienta para revisar qué quedó suelto antes de dar por buena una importación.

### Agregar un semestre nuevo

1. Dejar los `.csv` de matrícula y el `.xlsx` de docentes en `database/data/<año>-<semestre>/`.
2. En `AcademicStructureSeeder`, sumar esa carpeta a `DATA_DIRS` y el archivo de docentes a `FILES` en `TeachersSeeder`.
3. Si Moodle pegó el grupo a la cohorte —`Ciclo4BG1S22026` y `Ciclo4BG2S22026` son un solo Ciclo 4B con dos grupos—, mapearlas en `COHORT_ALIASES`.
4. Si el ciclo ocupa dos semestres, enlazarlo con el anterior en `PREVIOUS_CYCLES`.
5. Correr los dos seeders y revisar en el panel que ningún curso quede sin docente.

---

## Tests y estilo

```bash
composer test         # Pint en modo verificación + toda la suite
composer lint         # Formatea el código
composer lint:check   # Solo verifica, sin escribir
php artisan test      # Solo la suite
```

GitHub Actions corre ambos en cada push a `main` y en cada pull request. Si Pint falla, el build falla.

---

## Despliegue

La aplicación corre en un **cPanel de iFastNet** con CloudLinux. El despliegue es por git: la carpeta del servidor es una copia de trabajo del repositorio.

> Los datos concretos del servidor (usuario, host, puerto SSH, rutas) no están en este repositorio porque es público. Están en el `~/.ssh/config` local y en el manual privado de despliegue.

### Lo que git nunca va a traer

Estas cuatro cosas están en el `.gitignore` y se manejan aparte. Casi todo problema de despliegue sale de olvidar una:

| Qué | Cómo llega al servidor |
|---|---|
| `vendor/` | `composer install --no-dev` en el servidor |
| `public/build/` | `scp` desde tu PC después de `npm run build` |
| `.env` | Se edita a mano en el servidor, una sola vez |
| `database/data/` | `scp` solo cuando vayas a importar datos |

### Primera vez

Convertir la carpeta existente del servidor en una copia de trabajo, sin volver a subir nada:

```bash
cd ~/apps/iete-llano
git init
git remote add origin https://github.com/KevinEscobarV/ietellano.git
git fetch origin
git reset --hard origin/main
git branch -M main
git branch --set-upstream-to=origin/main main
```

Funciona en sitio porque `.env`, `vendor/`, `public/build/` y `storage/` están ignorados: `reset --hard` no los toca.

Composer no viene instalado en el hosting; se instala en el home del usuario:

```bash
mkdir -p ~/bin && cd ~
curl -sS https://getcomposer.org/installer | php
mv composer.phar ~/bin/composer && chmod +x ~/bin/composer
echo 'export PATH=$HOME/bin:$PATH' >> ~/.bashrc && source ~/.bashrc
composer --version
```

> El servidor usa **alt-php**: las rutas `/opt/cpanel/ea-phpXX` están vacías y el binario real es `/opt/alt/php84/usr/bin/php`. El selector de PHP de cPanel solo cambia el PHP del sitio web, no el de la consola; el de la consola se fija con ese `export PATH` en `~/.bashrc`.

### Cada cambio

**En tu PC:**

```bash
npm run build
composer test
git push origin main
```

Si el build cambió (CSS, JS o fuentes nuevas), súbelo — es lo que git no lleva:

```bash
scp -P <puerto> -r public/build <usuario>@<servidor>:~/apps/iete-llano/public/
```

Hacerlo **antes** del `git pull` es seguro: `scp` copia encima, no borra, y el sitio sigue funcionando mientras tanto.

**En el servidor:**

```bash
cd ~/apps/iete-llano
php artisan down
git pull
composer install --no-dev --optimize-autoloader   # solo si cambió composer.lock
php artisan migrate --force                        # solo si hay migraciones nuevas
php artisan optimize:clear
php artisan optimize
php artisan up
```

### Qué paso hace falta según lo que cambiaste

| Cambiaste | Necesitas |
|---|---|
| Vistas, CSS, JS, fuentes | `npm run build` + `scp` de `public/build` |
| Algo en `config/` | **`php artisan optimize:clear`** sin falta — si no, el servidor sigue leyendo la configuración vieja en caché |
| Rutas o vistas | `php artisan optimize:clear` |
| `composer.json` / `composer.lock` | `composer install --no-dev --optimize-autoloader` |
| Una migración nueva | `php artisan migrate --force` |
| Algo en `config/` (por ejemplo el tope de inasistencias) | editar el archivo y `php artisan optimize:clear` |
| Un rol o permiso nuevo | `php artisan db:seed --class=RolesAndPermissionsSeeder --force` (usa `firstOrCreate`, no pisa lo existente) |
| Solo código PHP | `git pull` y listo |

El paso que más se olvida es `optimize:clear` después de tocar `config/`. La configuración cacheada no se entera de que el archivo cambió, y el síntoma es desconcertante: el código dice una cosa y la aplicación hace otra.

### Cosas que conviene saber

- **Idioma.** `APP_LOCALE=es` está en el `.env`. Si el `.env` del servidor todavía dice `en`, la interfaz seguirá en inglés aunque el código esté bien; hay que editarlo allá y correr `optimize:clear`.
- **Correo.** `MAIL_MAILER=log`. Nada de lo que la aplicación "envíe" llega a destino. Tenlo presente antes de habilitar verificación de correo, invitaciones o avisos.
- **HTTPS.** En producción las URLs se fuerzan a `https` desde `AppServiceProvider`.
- **Zona horaria.** `America/Bogota`.
- **Logo de los PDF.** Se redimensiona y se cachea en `storage/`. Se regenera solo comparando fechas contra `public/images/logo.png`, así que basta con reemplazar esa imagen.

---

## Estructura

```
app/
├── Console/Commands/     Importación de notas desde xlsx
├── Http/Middleware/      EnsureUserIsTeacher: puerta del portal docente
├── Livewire/
│   ├── Dashboard.php     Tablero: avance de notas, matrícula, pendientes
│   ├── Admin/            Panel: usuarios, roles, estudiantes, docentes,
│   │                     estructura, notas, boletines, certificados
│   ├── Teacher/          Portal docente: MyCourses, Gradebook y Attendance
│   ├── Consulta/         Consulta pública: Lookup (documento) y Boletin
│   └── Settings/         Perfil, seguridad, apariencia
├── Policies/             CoursePolicy: quién puede calificar qué, y hasta cuándo
├── Enums/                AttendanceStatus: presente, falló, justificada
├── Services/             AttendanceService, BoletinService, CertificateService,
│                         GradeImporter, StudentLookup, TermService
│                         y sus exportadores a PDF
└── Support/              XlsxReader, ResizesInstitutionLogo

lang/es/                  Validación, autenticación y paginación en español
lang/es.json              Los textos sueltos del starter kit
routes/
├── web.php               Portada y tablero
├── admin.php             /admin/*  (rol super-admin o admin)
├── teacher.php           /docente/* (cuenta vinculada a un docente)
├── consulta.php          /consulta/* (público: documento y apellido)
└── settings.php          Ajustes de la cuenta
```
