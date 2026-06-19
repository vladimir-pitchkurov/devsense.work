---
title: "Vulnerabilidades y mitigaciones de aplicaciones web: SQLi, inyección de comandos, XSS, CSRF e IDOR | DevSense"
description: "Proteja sus aplicaciones PHP de vulnerabilidades web comunes. Aprenda a prevenir la inyección SQL, inyección de comandos, XSS, CSRF e IDOR con ejemplos de código seguro."
published: 2026-06-19
faq:
  - question: "¿Por qué las sentencias preparadas son seguras contra la inyección SQL?"
    answer: "Las sentencias preparadas envían la plantilla de la consulta SQL y los datos de los parámetros por separado al motor de la base de datos. El motor compila primero la consulta SQL, lo que garantiza que los datos de los parámetros nunca se analicen sintácticamente ni se ejecuten como comandos SQL, independientemente de los caracteres que contengan."
  - question: "¿Cuándo es seguro usar la directiva de blade sin escapar {!! $var !!} de Laravel?"
    answer: "Solo es seguro usar `{!! $var !!}` cuando la variable contiene HTML sin procesar que usted mismo ha generado o purificado mediante una biblioteca de sanitización HTML segura como HTMLPurifier. Nunca debe mostrar entrada de usuario sin procesar mediante esta directiva."
  - question: "¿Cómo previene el IDOR la búsqueda con alcance de relación (relation-scoped lookup)?"
    answer: "La búsqueda con alcance de relación (por ejemplo, `$user->orders()->findOrFail($id)`) garantiza que la consulta a la base de datos filtre de forma natural los resultados por el ID del usuario autenticado. Un atacante que intente acceder al ID de otro usuario recibirá un error 404 Not Found porque el registro no existe dentro de la relación acotada."
---

# Vulnerabilidades y mitigaciones de aplicaciones web: SQLi, inyección de comandos, XSS, CSRF e IDOR

Desarrollar aplicaciones web seguras no se trata de añadir seguridad al final del desarrollo. Requiere comprender cómo surgen las vulnerabilidades a nivel de código y diseñar límites para prevenirlas. 

En esta guía, analizaremos cinco vulnerabilidades críticas en aplicaciones web (inyección SQL, inyección de comandos, Cross-Site Scripting, CSRF e IDOR) en PHP y Laravel, veremos cómo se explotan e implementaremos mitigaciones seguras.

**Guías relacionadas:** [Arquitectura de monolito a microservicios](monolith-to-microservices-architecture) · [Observability & monitoring](observability-monitoring-laravel)

## Contenido

* [Inyección SQL (SQLi)](#sql-injection)
* [Inyección de comandos](#command-injection)
* [Cross-Site Scripting (XSS)](#xss)
* [Cross-Site Request Forgery (CSRF)](#csrf)
* [Referencias directas inseguras a objetos (IDOR)](#idor)
* [Errores comunes](#common-mistakes)
* [Lista de verificación](#checklist)
* [Resumen](#summary)
* [Cuestionario de autoevaluación](#self-test-quiz)

---

<a id="sql-injection"></a>
## Inyección SQL (SQLi)

La **inyección SQL** ocurre cuando la entrada no confiable del usuario se concatena directamente en las cadenas de consulta SQL. Esto permite a los atacantes manipular la estructura de la consulta, eludir la autenticación, leer contenido confidencial de la base de datos o eliminar registros.

### La forma incorrecta: concatenación de cadenas y ordenamiento inseguro

Aquí, el desarrollador concatena las variables de entrada directamente en la consulta y utiliza una entrada no sanitizada para definir la columna de ordenación.

```php
// app/Http/Controllers/ProductController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    public function search(Request $request): array
    {
        $search = $request->input('q');
        $sortBy = $request->input('sort', 'id');

        // VULNERABLE: SQL Injection via both search input and sorting column
        $sql = "SELECT * FROM products WHERE name LIKE '%{$search}%' ORDER BY {$sortBy} ASC";
        return DB::select($sql);
    }
}
```

### La forma correcta: sentencias preparadas y lista de permitidos para columnas

Para solucionar esto, debemos utilizar **sentencias preparadas (vinculación de parámetros / parameter binding)** para los valores de datos de la consulta. Dado que las columnas de ordenación no se pueden parametrizar, debemos validarlas frente a una **lista de permitidos (allowlist)** estricta.

```php
// app/Http/Controllers/ProductController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    private const ALLOWED_SORT_COLUMNS = ['id', 'name', 'price', 'created_at'];

    public function search(Request $request): array
    {
        $search = $request->input('q', '');
        
        // Strict sorting column allowlist fallback
        $sortBy = in_array($request->input('sort'), self::ALLOWED_SORT_COLUMNS, true) 
            ? $request->input('sort') 
            : 'id';

        // SECURE: Parameter binding for data, strict validation for identifiers
        return DB::select(
            "SELECT * FROM products WHERE name LIKE :search ORDER BY {$sortBy} ASC",
            ['search' => "%{$search}%"]
        );
    }
}
```

---

<a id="command-injection"></a>
## Inyección de comandos

La **inyección de comandos** ocurre cuando la entrada del usuario se pasa directamente a funciones de shell del sistema como `exec()`, `shell_exec()` o `system()`. Esto permite a los atacantes ejecutar comandos de shell arbitrarios en el servidor con los permisos de usuario del servidor web.

### La forma incorrecta: ejecución de shell con concatenación de cadenas

En este ejemplo, intentamos convertir un archivo PDF en una miniatura PNG utilizando una herramienta de línea de comandos, pero pasamos el nombre del archivo directamente a la shell.

```php
// app/Services/ThumbnailGenerator.php
declare(strict_types=1);

namespace App\Services;

class ThumbnailGenerator
{
    public function generate(string $filename): string
    {
        $outputPath = "/tmp/" . uniqid('thumb_', true) . ".png";
        
        // VULNERABLE: Command injection if filename contains shell operators (e.g. "; rm -rf /;")
        $cmd = "pdftoppm -png -r 150 {$filename} {$outputPath}";
        shell_exec($cmd);

        return $outputPath;
    }
}
```

### La forma correcta: componente Process con arreglos de argumentos

Nunca invoque la shell directamente ni pase argumentos concatenados. Utilice bibliotecas contenedoras de procesos (como Symfony Process) que manejen el escape y la ejecución de argumentos de forma segura sin utilizar un entorno de shell.

```php
// app/Services/ThumbnailGenerator.php
declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ThumbnailGenerator
{
    public function generate(string $filePath): string
    {
        $outputPath = "/tmp/" . uniqid('thumb_', true) . ".png";
        
        // SECURE: Arguments are passed as an array, bypassing the shell shell interpreter
        $process = new Process(['pdftoppm', '-png', '-r', '150', $filePath, $outputPath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputPath;
    }
}
```

---

<a id="xss"></a>
## Cross-Site Scripting (XSS)

El **Cross-Site Scripting (XSS)** ocurre cuando una aplicación incluye datos de usuario no confiables en una página web sin el escape adecuado. El navegador del atacante ejecuta JavaScript malicioso, que puede robar cookies de sesión, capturar entradas (keylogging) o redirigir a los usuarios.

Existen tres tipos de XSS:
- **XSS reflejado (Reflected XSS):** El script forma parte de la carga útil de la solicitud y se devuelve reflejado inmediatamente en la respuesta.
- **XSS almacenado (Stored XSS):** El script se guarda en la base de datos (por ejemplo, el texto de un comentario) y se ejecuta cuando otros usuarios ven la página.
- **XSS basado en DOM (DOM-based XSS):** La vulnerabilidad existe puramente en el JavaScript del lado del cliente que ejecuta variables DOM no confiables.

### La forma incorrecta: imprimir contenido de usuario sin escapar

El `{!! $var !!}` de Laravel genera HTML sin procesar, eludiendo el escape predeterminado contra XSS de Blade.

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- VULNERABLE: Stored XSS if bio contains <script>alert('xss')</script> -->
    {!! $user->bio !!}
</div>
```

### La forma correcta: escapar por defecto y purificar el texto enriquecido

Utilice siempre `{{ $var }}`, que ejecuta automáticamente `e()` (`htmlspecialchars` de PHP) para escapar la salida. Si debe mostrar texto enriquecido proporcionado por el usuario, páselo por una biblioteca de sanitización HTML segura (como HTMLPurifier).

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- SECURE: Automatically escaped by Blade -->
    {{ $user->bio }}
</div>

<div class="user-rich-content">
    <!-- SECURE: Outputting raw html only AFTER strict HTML purification -->
    {!! clean($user->rich_description) !!}
</div>
```

---

<a id="csrf"></a>
## Cross-Site Request Forgery (CSRF)

El **Cross-Site Request Forgery (CSRF)** obliga al navegador de un usuario autenticado a ejecutar acciones que cambian el estado (como modificar la contraseña o iniciar pagos) en una aplicación en la que tiene sesión iniciada actualmente. El navegador adjunta automáticamente las cookies de sesión a las solicitudes entre sitios, lo que valida la solicitud.

### La forma incorrecta: acciones que cambian el estado en solicitudes GET

Las solicitudes GET siempre deben ser **idempotentes** (seguras de ejecutar varias veces sin modificar el estado). Realizar actualizaciones en rutas GET hace que el CSRF sea trivial.

```php
// routes/web.php
// VULNERABLE: Anyone can link to /profile/delete in an img tag to delete an account
Route::get('/profile/delete', [ProfileController::class, 'destroy']);
```

### La forma correcta: rutas POST/DELETE con tokens CSRF

Utilice siempre métodos HTTP que cambien el estado (POST, PUT, DELETE) y requieran un token secreto y criptográficamente seguro que no pueda ser adivinado por sitios web externos.

```blade
<!-- resources/views/profile.blade.php -->
<!-- SECURE: Form triggers a POST request and generates a hidden CSRF token -->
<form action="{{ route('profile.destroy') }}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit">Delete Account</button>
</form>
```

---

<a id="idor"></a>
## Referencias directas inseguras a objetos (IDOR)

El **IDOR (Insecure Direct Object Reference)** ocurre cuando una aplicación expone una clave de base de datos o un ID de recurso directamente a los usuarios, y no verifica si el usuario que realiza la solicitud tiene permiso para acceder a ese recurso específico.

### La forma incorrecta: búsqueda de consulta global sin autorización

En este ejemplo, cualquier usuario que haya iniciado sesión puede acceder a los detalles de la factura de otro usuario simplemente cambiando el `{id}` en el parámetro de la URL.

```php
// app/Http/Controllers/InvoiceController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController
{
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        // VULNERABLE: Retrieves invoice without checking user relationship or authorization
        $invoice = Invoice::findOrFail($id);
        
        return response()->json($invoice);
    }
}
```

### La forma correcta: consultas con alcance de relación y autorización mediante Gate

Desacople la obtención de recursos cargándolos a través del alcance del modelo de relación del usuario, o verifique los permisos utilizando Gates/Policies de autorización.

```php
// app/Http/Controllers/InvoiceController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceController
{
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        // SECURE Method A: Query scoped directly to the authenticated user
        $invoice = $request->user()->invoices()->findOrFail($id);

        // SECURE Method B: Global lookup but validated with a Laravel Policy
        // $invoice = Invoice::findOrFail($id);
        // Gate::authorize('view', $invoice);
        
        return response()->json($invoice);
    }
}
```

---

## Errores comunes

1. **Escapar en lugar de parametrizar:** Pensar que ejecutar `addslashes()` o expresiones regulares simples en las variables protege las consultas. Parametrice siempre.
2. **Alteraciones de estado mediante GET:** Crear endpoints de API/web que realizan acciones de eliminación o actualización utilizando el método HTTP GET.
3. **Mega-pasarelas (Mega-Gateways) que contienen validación:** Dejar la validación de XSS/SQLi en manos de WAF o gateways en lugar de codificar una validación robusta directamente en el controlador de la aplicación.
4. **IDOR en solicitudes AJAX:** Asegurar las rutas principales de las páginas HTML pero exponer IDs de recursos no autorizados en endpoints JSON de AJAX/API.

---

## Lista de verificación

1. **Seguridad de las consultas:** ¿Está concatenando variables dentro de sentencias sin procesar `DB::select` o `whereRaw`?
2. **Ejecución de shell:** ¿Puede reemplazar los comandos de shell con bibliotecas estándar de PHP? Si no es así, ¿se pasan los argumentos en un arreglo?
3. **Salida de Blade:** ¿Está utilizando `{!! !!}` en algún lugar de las plantillas de Blade sin la validación de HTMLPurifier?
4. **Verificación de autorización:** ¿Cada método del controlador que carga un recurso verifica si el recurso pertenece al usuario actual?

---

## Resumen

Las aplicaciones web seguras validan las entradas de forma estricta y aplican defensa en profundidad. Prevenga la **inyección SQL** utilizando sentencias preparadas y listas de permitidos para las columnas. Evite la **inyección de comandos** utilizando argumentos en forma de arreglo dentro de contenedores de procesos. Bloquee el **XSS** escapando las salidas de variables mediante motores de plantillas. Detenga el **CSRF** aislando las mutaciones de estado a solicitudes POST/DELETE protegidas con tokens. Derrote el **IDOR** limitando el alcance de las búsquedas a las relaciones de usuarios autenticados o ejecutando verificaciones de políticas.

---

## Cuestionario de autoevaluación

### Pregunta 1: ¿Cuál es la diferencia principal entre el escape y la parametrización para las consultas SQL?
- A) El escape se ejecuta en el servidor de la base de datos, mientras que la parametrización se maneja dentro del motor de PHP.
- B) El escape cambia los caracteres especiales dentro de las cadenas de consulta para hacerlas seguras, mientras que la parametrización envía la estructura de la consulta y los parámetros por separado al motor de la base de datos.
- C) La parametrización solo es compatible con las bases de datos PostgreSQL.

<details>
<summary>Haga clic para ver la respuesta</summary>

**Respuesta: B**
El escape es un proceso de manipulación de cadenas que es propenso a errores del analizador sintáctico (parser) y a evasiones. La parametrización es una característica del protocolo en la que la estructura SQL y los valores de los datos se envían de forma independiente, garantizando que los datos nunca puedan alterar la plantilla de la consulta.
</details>

### Pregunta 2: ¿Por qué las solicitudes GET son vulnerables a CSRF incluso si están protegidas por tokens?
- A) Los navegadores no admiten formularios GET.
- B) Las solicitudes GET exponen los tokens en el historial de URLs, los registros del navegador y las cabeceras Referer, y de todos modos nunca deberían utilizarse para operaciones que cambien el estado.
- C) Las solicitudes GET eluden automáticamente el middleware de verificación de tokens CSRF de Laravel.

<details>
<summary>Haga clic para ver la respuesta</summary>

**Respuesta: B**
Las solicitudes GET están diseñadas para ser idempotentes (lecturas seguras). Si una solicitud GET cambia el estado, los atacantes pueden incrustar la URL de destino en enlaces entre sitios o etiquetas de imagen, activando el cambio de estado automáticamente sin el consentimiento del usuario.
</details>

### Pregunta 3: ¿Cómo previene el IDOR el limitar el alcance de una consulta de base de datos a una relación de usuario?
- A) Encripta las claves primarias de la base de datos.
- B) Bloquea la solicitud a nivel de firewall.
- C) Garantiza que la consulta SQL filtre de forma natural los registros utilizando el ID del usuario autenticado como una restricción de búsqueda, haciendo que los registros de otros usuarios sean inaccesibles.

<details>
<summary>Haga clic para ver la respuesta</summary>

**Respuesta: C**
Acotar las consultas (por ejemplo, `$user->invoices()->findOrFail($id)`) añade una cláusula `WHERE user_id = ?` a la consulta SQL. Si un atacante intenta obtener un ID de recurso que pertenece a otra persona, la consulta devolverá cero registros, lo que resultará en un error 404 seguro.
</details>
