---
title: "Conceptos básicos de PHP y variables globales: Ámbito y superglobales | DevSense"
description: "Una guía completa sobre los ámbitos de variables en PHP (local, global, estático) y todas las superglobales. Aprenda mejores prácticas, trampas de seguridad y restricciones de PHP 8.1+."
faq:
  - question: "¿Cuál es la diferencia entre los ámbitos de variable local, global y estático en PHP?"
    answer: "Las variables locales existen solo dentro de la función en la que se declaran. Las variables globales existen fuera de las funciones y se debe acceder a ellas mediante la palabra clave 'global' o el array $GLOBALS. Las variables estáticas existen localmente pero retienen su valor a través de múltiples ejecuciones de la función."
  - question: "¿Por qué falla la reasignación de la variable $GLOBALS en PHP 8.1+?"
    answer: "Desde PHP 8.1, Zend Engine impide la reasignación del array $GLOBALS (por ejemplo, $GLOBALS = [] está prohibido) para optimizar la velocidad de búsqueda de variables y garantizar la consistencia interna. Aún se pueden modificar claves individuales (por ejemplo, $GLOBALS['myVar'] = 'value')."
  - question: "¿Cómo debo manejar la subida segura de archivos usando $_FILES en PHP?"
    answer: "Verifique siempre el estado de la subida con $_FILES['field']['error'], valide el tamaño del archivo y el tipo MIME en el servidor, verifique el archivo con is_uploaded_file() y muévalo a un directorio seguro usando move_uploaded_file()."
  - question: "¿Por qué se desaconseja el acceso directo a $_GET o $_POST en el desarrollo PHP moderno?"
    answer: "Acceder directamente a las superglobales acopla su código al estado global, lo que dificulta enormemente las pruebas unitarias y la simulación (mocking) de peticiones. También elude el filtrado, aumentando el riesgo de ataques XSS e inyección SQL. Use un wrapper de petición (request wrapper) en su lugar."
---

# PHP Basics: Variable Scopes and Superglobals

**Difficulty Level:** Junior  
**Target PHP Versions:** PHP 7.0+ (with features marked up to PHP 8.3+)

Imagine desplegar una refactorización de código menor, solo para descubrir que su entorno de producción se cae con una excepción fatal en tiempo de ejecución: `Fatal error: Cannot reassign $GLOBALS`. O tal vez escribió un formulario de contacto simple, solo para descubrir que un usuario malintencionado inyectó una carga útil de XSS directamente a través de `$_POST` y secuestró las sesiones de administrador.

En PHP, los ámbitos de las variables y las superglobales son la base del flujo de datos. Sin embargo, siguen siendo una fuente frecuente de contaminación de ámbitos (scope pollution), fugas de memoria y vulnerabilidades críticas de seguridad para los desarrolladores que se inician en el lenguaje. Comprender cómo viajan las variables entre ámbitos y cómo se comportan las superglobales de PHP —especialmente bajo las estrictas reglas de los motores PHP modernos— es esencial para escribir código seguro, testeable y robusto.

---

## Variable Scopes: Local, Global, and Static

El ámbito (scope) define la visibilidad y el ciclo de vida de las variables en su código. En PHP, las variables no se filtran automáticamente a ámbitos anidados a menos que se indique explícitamente.

### Local Scope
* **Point**: Las variables declaradas dentro de una función o método de clase están aisladas del entorno exterior.
* **Why it matters**: El ámbito local evita colisiones de nombres y mutaciones inesperadas. Una variable llamada `$user` dentro de una función no puede sobrescribir accidentalmente una variable `$user` definida en otro lugar.
* **Example**:
  ```php
  // app/Services/CalculationService.php
  
  function calculateTotal(float $price, float $tax): float
  {
      $total = $price + ($price * $tax); // Local variable
      return $total;
  }
  
  // Accessing $total here will throw a Warning (Undefined variable $total)
  ```
* **Consequence**: Una vez que la función termina su ejecución, sus variables locales se destruyen y su memoria se libera. Cualquier intento de acceder a ellas desde el ámbito global da como resultado un aviso de variable indefinida.

### Global Scope
* **Point**: Las variables definidas fuera de cualquier función o clase pertenecen al ámbito global. Sin embargo, no se puede acceder a ellas automáticamente dentro de las funciones.
* **Why it matters**: Las variables globales permiten compartir configuraciones o conexiones a bases de datos entre diferentes archivos, pero acceder a ellas dentro de funciones requiere palabras clave explícitas.
* **Example**:
  ```php
  // config/database.php
  
  $dbDsn = "mysql:host=127.0.0.1;dbname=app_db";
  
  function connect(): PDO
  {
      // Accessing global scope requires the 'global' keyword
      global $dbDsn; 
      
      return new PDO($dbDsn, "root", "secret");
  }
  ```
* **Consequence**: Depender de la palabra clave `global` introduce dependencias ocultas. Hace que las pruebas unitarias sean extremadamente difíciles porque el ejecutor de pruebas no puede aislar o simular (mock) fácilmente los valores globales.

### Static Scope
* **Point**: Una variable estática se declara dentro de una función utilizando la palabra clave `static`. Existe solo dentro del ámbito local de esa función, pero conserva su valor entre ejecuciones subsiguientes de la función.
* **Why it matters**: Las variables estáticas son perfectas para almacenar en caché cálculos costosos, realizar un seguimiento de la profundidad de la recursión o generar identificadores de secuencia ligeros sin contaminar el espacio de nombres global.
* **Example**:
  ```php
  // app/Utils/SequenceGenerator.php
  
  function getNextSequenceId(): int
  {
      static $id = 0; // Initialized only on the first call
      $id++;
      return $id;
  }
  
  echo getNextSequenceId(); // 1
  echo getNextSequenceId(); // 2
  ```
  > [!NOTE]
  > **Nota sobre la versión de PHP**: Desde PHP 8.3+, las variables estáticas se pueden inicializar con expresiones dinámicas (por ejemplo, llamando a otras funciones). Antes de PHP 8.3, solo se podían inicializar con valores constantes o literales.
* **Consequence**: La variable persiste en memoria durante la duración del proceso PHP actual. Si su aplicación se ejecuta bajo entornos de ejecución persistentes (como RoadRunner o FrankenPHP), las variables estáticas mantendrán el estado a través de diferentes peticiones HTTP, lo que puede causar fugas de memoria o mezclar datos de usuarios si no se limpian.

---

## PHP Superglobals

Las superglobales son arrays asociativos integrados que siempre están disponibles en todos los ámbitos a lo largo del ciclo de vida del script. No necesita usar la palabra clave `global` para acceder a ellos.

### 1. `$GLOBALS` (con restricciones de reasignación en PHP 8.1+)
* **Point**: Un array asociativo que contiene referencias a todas las variables definidas actualmente en el ámbito global del script.
* **Why it matters**: Proporciona acceso programático y dinámico al ámbito global sin declarar `global $varName`.
* **Example**:
  ```php
  // app/Utils/DebugHelper.php
  
  $debugMode = true;
  
  function isDebugEnabled(): bool
  {
      // Directly check the global variable through the superglobal array
      return $GLOBALS['debugMode'] ?? false;
  }
  ```
* **Restricción de PHP 8.1+**:
  Antes de PHP 8.1, el array `$GLOBALS` se podía reasignar o pasar por referencia. En PHP 8.1+, reasignar todo el array `$GLOBALS` (por ejemplo, `$GLOBALS = [];` o `$GLOBALS =& $otherArray;`) está prohibido y lanza un error fatal en tiempo de compilación o ejecución. Esta restricción se introdujo para optimizar la velocidad de búsqueda de variables en Zend Engine. Aún se pueden modificar claves individuales:
  ```php
  // app/Utils/LegacyRunner.php
  
  // This throws a Compile Error in PHP 8.1+:
  // $GLOBALS = ['app_env' => 'production']; 
  
  // This is fully supported and correct:
  $GLOBALS['app_env'] = 'production'; 
  ```
* **Consequence**: Las bases de código heredadas que restablecían el estado global durante la finalización de las pruebas unitarias mediante `$GLOBALS = []` deben reescribirse para restablecer las variables clave por clave.

### 2. `$_SERVER`
* **Point**: Contiene cabeceras, rutas, ubicaciones de scripts y variables de entorno del servidor web.
* **Why it matters**: Esencial para el enrutamiento de peticiones, verificar los métodos de petición (GET, POST) y validar las cabeceras HTTP.
* **Example**:
  ```php
  // public/index.php
  
  $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
  $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  ```
* **Consequence**: Los valores con el prefijo `HTTP_` (por ejemplo, `HTTP_USER_AGENT`, `HTTP_X_FORWARDED_FOR`) son proporcionados directamente por el navegador del cliente. Nunca se debe confiar ciegamente en ellos, ya que pueden ser fácilmente falsificados.

### 3. `$_GET`
* **Point**: Un array asociativo de variables de la cadena de consulta (query string) pasadas al script a través de la URL.
* **Why it matters**: Se utiliza para pasar parámetros de navegación no confidenciales, como offsets de paginación, filtros y términos de búsqueda.
* **Example**:
  ```php
  // app/Controllers/SearchController.php
  
  // URL: /search.php?query=php&page=2
  $query = $_GET['query'] ?? '';
  $page = (int)($_GET['page'] ?? 1); // Cast to int for safety
  ```
* **Consequence**: Los parámetros de consulta se almacenan en el historial del navegador y en los registros del servidor web. Nunca pase credenciales confidenciales (contraseñas, claves de API o tokens de restablecimiento) a través de `$_GET`.

### 4. `$_POST`
* **Point**: Un array asociativo de variables pasadas a través de HTTP POST (por ejemplo, formularios HTML o cargas útiles `application/x-www-form-urlencoded`).
* **Why it matters**: Se utiliza para transmitir grandes conjuntos de datos, metadatos de archivos y cargas útiles confidenciales que modifican datos en el servidor.
* **Example**:
  ```php
  // app/Controllers/RegistrationController.php
  
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // PHP 8.0+ allows throw expressions with null coalescing:
      $email = $_POST['email'] ?? throw new InvalidArgumentException('Email is required');
      $password = $_POST['password'] ?? '';
  }
  ```
* **Consequence**: Si un usuario envía un formulario cuya carga útil supera la configuración `post_max_size` en `php.ini`, PHP descarta silenciosamente los datos. El array `$_POST` estará vacío y no se lanza ningún error automáticamente.

### 5. `$_SESSION`
* **Point**: Un array asociativo que contiene variables de sesión disponibles para el script actual.
* **Why it matters**: Permite persistir la identidad y el estado del usuario (como carritos de compra o el estado de inicio de sesión) a través de múltiples peticiones HTTP sin estado.
* **Example**:
  ```php
  // app/Services/AuthenticationService.php
  
  // PHP 7.0+ allows passing runtime options to session_start()
  session_start([
      'cookie_httponly' => true,
      'cookie_secure' => true,
      'cookie_samesite' => 'Lax',
  ]);
  
  $_SESSION['is_logged_in'] = true;
  $_SESSION['username'] = 'john_doe';
  ```
* **Consequence**: Se debe llamar a `session_start()` antes de acceder a `$_SESSION`. Si las cookies de sesión no están configuradas con la bandera `HttpOnly`, el ID de sesión puede ser robado mediante XSS, comprometiendo las cuentas de usuario.

### 6. `$_COOKIE`
* **Point**: Un array asociativo que contiene las cookies enviadas al servidor por el navegador del usuario.
* **Why it matters**: Permite leer datos de preferencia del lado del cliente o tokens de autenticación de tipo "Recordarme".
* **Example**:
  ```php
  // app/Services/ThemeService.php
  
  $userTheme = $_COOKIE['preferred_theme'] ?? 'dark';
  ```
* **Consequence**: Las cookies se almacenan completamente en el lado del cliente. Un usuario puede modificar los valores de las cookies manualmente en su navegador. Nunca confíe en los valores de `$_COOKIE` para lógica crítica de negocio (como `$_COOKIE['is_admin'] = 1`).

### 7. `$_ENV`
* **Point**: Un array asociativo que contiene variables de entorno pasadas al proceso PHP.
* **Why it matters**: Mantiene las credenciales confidenciales (contraseñas de base de datos, claves de API de correo) seguras y separadas del código fuente.
* **Example**:
  ```php
  // app/Config/Database.php
  
  $dbPassword = $_ENV['DB_PASSWORD'] ?? 'default_password';
  ```
* **Consequence**: `$_ENV` puede estar completamente vacío si la directiva `variables_order` en `php.ini` no contiene el carácter `"E"` (por ejemplo, si está configurada como `"GPCS"`). Si `$_ENV` está vacío, use `getenv()` o actualice su configuración.

### 8. `$_FILES`
* **Point**: Un array asociativo que contiene metadatos sobre los archivos subidos al servidor mediante HTTP POST.
* **Why it matters**: Proporciona acceso seguro a las propiedades del archivo subido por el cliente (nombre, tipo, tamaño, tmp_name, error) para validarlo y moverlo.
* **Example**:
  ```php
  // app/Services/UploadService.php
  
  if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
      $tempPath = $_FILES['profile_pic']['tmp_name'];
      
      // Strict security validation
      if (is_uploaded_file($tempPath)) {
          $filename = uniqid('avatar_', true) . '.png';
          move_uploaded_file($tempPath, '/var/www/uploads/' . $filename);
      }
  }
  ```
* **Consequence**: Si los archivos no se mueven usando `move_uploaded_file()` durante la ejecución de la petición, PHP elimina automáticamente el archivo temporal del directorio temporal una vez que finaliza el script.

---

## Limitations, Trade-offs, and Clean Architecture

Aunque las variables globales y las superglobales están integradas en PHP para facilitar su uso, las prácticas modernas de ingeniería de software desaconsejan su uso directo en la lógica de negocio.

* **Lack of Testability**: El acceso directo a `$_GET`, `$_POST` o `$_SERVER` acopla su clase de servicio directamente al estado global. Esto hace que las pruebas unitarias sean imposibles porque no puede aislar el contexto de ejecución sin modificar las variables globales antes de cada prueba.
* **Security Exposure**: Las superglobales representan entradas del cliente sin depurar y no confiables. Acceder directamente a `$_POST['username']` en lugar de utilizar un objeto de transferencia de datos validado aumenta el área de superficie para inyección SQL y Cross-Site Scripting (XSS).
* **The Framework Alternative**: Los frameworks PHP modernos (como Laravel y Symfony) envuelven las superglobales en objetos Request y Response. En lugar de leer `$_GET['id']`, se inyecta una instancia de `Request`:
  ```php
  // app/Http/Controllers/UserController.php
  
  // Modern framework approach using Dependency Injection
  public function show(Request $request): Response
  {
      $userId = $request->query('id'); // Mockable and testable
      // ...
  }
  ```

---

## Practical Takeaways

1. **Minimize Scope**: Mantenga las variables lo más locales posible. Pase variables a las funciones como argumentos en lugar de acceder a ellas mediante la palabra clave `global`.
2. **Handle RoadRunner/FrankenPHP Lifecycles**: Si utiliza variables estáticas, límpielas o restablezcalas si su entorno utiliza procesos de trabajo (workers) persistentes. De lo contrario, se producirán fugas de memoria o fugas de datos entre usuarios.
3. **Use Request Wrappers**: Si está escribiendo PHP nativo (sin framework), cree una clase wrapper o use `filter_input()` en lugar de acceder directamente a las superglobales.
4. **Prepare for PHP 8.1+**: Asegúrese de no tener código de bibliotecas heredadas que reasigne o pase `$GLOBALS` por referencia.

---

## Self-Check Quiz

Ponga a prueba su comprensión de los ámbitos y las superglobales en PHP.

### Question 1: ¿Cuál es el resultado del siguiente código PHP?
```php
// app/Http/Controllers/TestController.php

$name = "Alice";

function greet(): string
{
    return "Hello, " . $name;
}

echo greet();
```
- A) `Hello, Alice`
- B) `Hello, ` junto con un aviso de variable indefinida (Undefined Variable Warning)
- C) `Hello, $name`

<details>
<summary>Haga clic para ver la respuesta y la explicación</summary>

**Respuesta: B**  
Las variables definidas en el ámbito global no son visibles automáticamente dentro de las funciones. Dado que `$name` no está declarada con la palabra clave `global` ni se accede a ella a través de `$GLOBALS['name']` dentro de `greet()`, PHP lanza un aviso de variable indefinida y trata la variable como `null`.
</details>

### Question 2: ¿Qué afirmación es cierta con respecto a la reasignación de `$GLOBALS`?
- A) `$GLOBALS` se puede reasignar en todas las versiones de PHP.
- B) Reasignar `$GLOBALS` (por ejemplo, `$GLOBALS = [];`) provocará un error fatal a partir de PHP 8.1+.
- C) La modificación de claves individuales como `$GLOBALS['user'] = 'Bob';` está obsoleta y deshabilitada en PHP 8.1+.

<details>
<summary>Haga clic para ver la respuesta y la explicación</summary>

**Respuesta: B**  
A partir de PHP 8.1, ya no se puede reasignar el propio array `$GLOBALS` debido a optimizaciones en la tabla de símbolos de Zend Engine. Sin embargo, la modificación de claves individuales (como `$GLOBALS['user'] = 'Bob'`) sigue estando totalmente admitida y operativa.
</details>

### Question 3: ¿Por qué debería evitar confiar en `$_FILES['input_name']['type']`?
- A) Siempre está vacío.
- B) PHP lo determina en el lado del servidor y con frecuencia es incorrecto.
- C) Es enviado por el navegador (cliente) y un atacante puede falsificarlo.

<details>
<summary>Haga clic para ver la respuesta y la explicación</summary>

**Respuesta: C**  
El tipo MIME en `$_FILES['...']['type']` lo proporcionan directamente las cabeceras de petición del navegador del cliente. Un atacante puede subir un script `.php` malintencionado pero falsificar la cabecera para que se lea `image/png`. Verifique siempre el tipo MIME en el servidor utilizando `finfo_file()` o la extensión `fileinfo`.
</details>
