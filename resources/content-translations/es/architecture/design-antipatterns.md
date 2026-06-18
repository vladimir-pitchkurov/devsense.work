---
title: "Antipatrones de diseño de software: Spaghetti, God Object, Golden Hammer y Cargo Cult | DevSense"
description: "Domina el diseño de software identificando y evitando antipatrones comunes en PHP. Aprende a refactorizar Código Spaghetti, God Objects, Golden Hammer, Optimización Prematura y Cargo Culting."
published: 2026-06-18
faq:
  - question: "¿Qué es un antipatrón de diseño de software?"
    answer: "Un antipatrón de diseño de software es una respuesta común y recurrente a un problema que parece beneficiosa en la superficie, pero que da como resultado consecuencias muy negativas, difíciles de mantener y contraproducentes."
  - question: "¿Cómo viola un God Object los principios SOLID?"
    answer: "Un God Object viola el Principio de Responsabilidad Única (SRP) al concentrar demasiadas responsabilidades, acciones y datos en una sola clase. También viola el Principio de Abierto/Cerrado (OCP) porque modificar una característica requiere cambiar esta clase monolítica central."
  - question: "¿Cuándo se considera el patrón Repository como programación de culto al cargo (cargo cult)?"
    answer: "Se considera programación de culto al cargo cuando escribes una interfaz y una clase de repositorio genérico que simplemente envuelve las consultas de Eloquent (por ejemplo, find, create, delete) sin añadir ninguna abstracción de negocio real, beneficios de pruebas o valor de desacoplamiento."
---

# Antipatrones de diseño de software: Spaghetti, God Object, Golden Hammer y Cargo Cult

Escribir código limpio consiste tanto en saber qué *no* hacer como en saber qué hacer. Los antipatrones de diseño de software son malas prácticas comunes y recurrentes que inicialmente parecen buenas soluciones, pero que conducen a bases de código inmantenibles, frágiles y sobreingenierizadas.

En esta guía, analizaremos cinco antipatrones de diseño principales en el desarrollo moderno de PHP, veremos ejemplos concretos de cómo se manifiestan y repasaremos cómo refactorizarlos en estructuras limpias y mantenibles.

**Guías relacionadas:** [Patrones de diseño de creación GoF](creational-design-patterns) · [Patrones de diseño estructurales GoF](structural-design-patterns) · [Patrones de diseño de comportamiento GoF](behavioral-design-patterns)

## Contenidos

* [Código Spaghetti](#spaghetti-code)
* [El God Object](#god-object)
* [El Golden Hammer](#golden-hammer)
* [Optimización prematura](#premature-optimization)
* [Cargo Cult y programación de copiar y pegar](#cargo-cult)
* [Errores comunes](#common-mistakes)
* [Lista de verificación](#checklist)
* [Resumen](#summary)
* [Prueba de autoevaluación](#self-test-quiz)

---

<a id="spaghetti-code"></a>
## Código Spaghetti

El **Código Spaghetti** (código espagueti) es código con un flujo de control enredado y no estructurado, lleno de bloques condicionales anidados, responsabilidades mezcladas y falta de límites arquitectónicos claros. Se llama "spaghetti" porque el flujo de ejecución se enreda como un plato de fideos, lo que hace imposible de rastrear.

### La forma incorrecta: Base de datos, validación y HTML mezclados

En este ejemplo de PHP, el enrutamiento, la ejecución de la base de datos, la validación de entradas y la renderización están mezclados en un solo archivo.

```php
// index.php
<?php
$conn = new mysqli("localhost", "root", "", "app");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["email"]) && filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
        $email = $_conn->real_escape_string($_POST["email"]);
        $res = $conn->query("SELECT * FROM users WHERE email = '$email'");
        if ($res->num_rows === 0) {
            $conn->query("INSERT INTO users (email) VALUES ('$email')");
            echo "<p>User registered!</p>";
        } else {
            echo "<p>Email already exists.</p>";
        }
    } else {
        echo "<p>Invalid email.</p>";
    }
}
?>
<form method="POST">
    <input type="text" name="email" />
    <button type="submit">Register</button>
</form>
```

### La forma correcta: Separación de conceptos

Refactorizamos esto separando la interacción con la base de datos (Repositorio), las reglas de negocio/validación (Controlador) y la presentación visual (Blade/Plantilla).

```php
// app/Repositories/UserRepository.php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $email): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO users (email) VALUES (:email)");
        return $stmt->execute(['email' => $email]);
    }
}
```

```php
// app/Http/Controllers/RegisterController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use InvalidArgumentException;

class RegisterController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(array $data): string
    {
        $email = $data['email'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format.");
        }

        if ($this->userRepository->findByEmail($email) !== null) {
            return "Email already exists.";
        }

        $this->userRepository->create($email);
        return "User registered!";
    }
}
```

---

<a id="god-object"></a>
## El God Object

Un **God Object** (objeto dios) es una clase monolítica que sabe demasiado o hace demasiado. Concentra toda la inteligencia de la aplicación, violando el **Principio de Responsabilidad Única (SRP)**. Otras clases en el sistema se convierten en meros contenedores de datos (modelos anémicos) controlados por esta clase gigante.

### La forma incorrecta: Un OrderManager monolítico

Aquí, una sola clase maneja la conexión a la pasarela de pago, el guardado en la base de datos, el envío de correos electrónicos, los cálculos de descuento y la generación de PDF.

```php
// app/Services/OrderManager.php
declare(strict_types=1);

namespace App\Services;

class OrderManager
{
    public function processOrder(array $orderData): void
    {
        // 1. Calculate discount
        $total = $orderData['price'];
        if ($orderData['coupon'] === 'SUMMER') {
            $total *= 0.9;
        }

        // 2. Save order to database
        $db = new \PDO("mysql:host=localhost;dbname=shop", "root", "");
        $stmt = $db->prepare("INSERT INTO orders (total, user_id) VALUES (?, ?)");
        $stmt->execute([$total, $orderData['user_id']]);

        // 3. Process payment via Stripe
        $stripe = new \Stripe\StripeClient('sk_test_key');
        $stripe->charges->create([
            'amount' => (int)($total * 100),
            'currency' => 'usd',
            'source' => $orderData['token'],
        ]);

        // 4. Generate PDF invoice
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->Write(10, "Invoice total: " . $total);
        $pdf->Output('F', "/invoices/order.pdf");

        // 5. Send email notification
        mail($orderData['email'], "Order Success", "Your order total is " . $total);
    }
}
```

### La forma correcta: Servicios desacoplados

Refactorizamos delegando cada tarea del dominio a un componente dedicado, manteniendo la clase administradora (manager) como un coordinador ligero.

```php
// app/Services/OrderService.php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Notification\NotificationInterface;
use App\Services\Invoice\InvoiceGeneratorInterface;

class OrderService
{
    private OrderRepository $repository;
    private DiscountCalculator $discountCalculator;
    private PaymentGatewayInterface $paymentGateway;
    private InvoiceGeneratorInterface $invoiceGenerator;
    private NotificationInterface $notifier;

    public function __construct(
        OrderRepository $repository,
        DiscountCalculator $discountCalculator,
        PaymentGatewayInterface $paymentGateway,
        InvoiceGeneratorInterface $invoiceGenerator,
        NotificationInterface $notifier
    ) {
        $this->repository = $repository;
        $this->discountCalculator = $discountCalculator;
        $this->paymentGateway = $paymentGateway;
        $this->invoiceGenerator = $invoiceGenerator;
        $this->notifier = $notifier;
    }

    public function checkout(array $orderData): void
    {
        $total = $this->discountCalculator->calculate($orderData['price'], $orderData['coupon']);
        
        $order = $this->repository->save($total, $orderData['user_id']);
        
        $this->paymentGateway->charge($total, $orderData['token']);
        
        $invoicePath = $this->invoiceGenerator->generate($order);
        
        $this->notifier->sendSuccessNotification($orderData['email'], $total, $invoicePath);
    }
}
```

---

<a id="golden-hammer"></a>
## El Golden Hammer

El **Golden Hammer** (martillo de oro) es la suposición de que una tecnología o patrón de diseño favorito es de aplicación universal: *"Si la única herramienta que tienes es un martillo, todo lo que ves parece un clavo."*

En PHP, esto sucede a menudo cuando los desarrolladores intentan usar patrones estructurales/de comportamiento complejos (por ejemplo, State o Visitor) para tareas sencillas, lo que provoca una explosión innecesaria de clases, o cuando utilizan un motor de base de datos (como Elasticsearch o Redis) para tareas que podrían realizarse fácilmente de forma directa en SQL.

### La forma incorrecta: Forzar el patrón State para una comprobación de edad simple

Un desarrollador quiere comprobar si un usuario tiene permitido comprar alcohol. En lugar de usar una condición simple, construye toda una maquinaria del patrón State con múltiples interfaces y clases concretas.

```php
// app/Validation/AgeValidator.php
declare(strict_types=1);

namespace App\Validation;

interface AgeStateInterface {
    public function canPurchaseAlcohol(): bool;
}

class UnderageState implements AgeStateInterface {
    public function canPurchaseAlcohol(): bool { return false; }
}

class AdultState implements AgeStateInterface {
    public function canPurchaseAlcohol(): bool { return true; }
}

class AgeValidator
{
    private AgeStateInterface $state;

    public function __construct(int $age)
    {
        $this->state = $age >= 18 ? new AdultState() : new UnderageState();
    }

    public function check(): bool
    {
        return $this->state->canPurchaseAlcohol();
    }
}
```

### La forma correcta: Manténlo simple (KISS)

Los patrones de diseño añaden complejidad y carga cognitiva. Si la lógica de negocio es simple, escríbela de manera simple.

```php
// app/Validation/AgeValidator.php
declare(strict_types=1);

namespace App\Validation;

class AgeValidator
{
    private const ALCOHOL_MINIMUM_AGE = 18;

    public static function canPurchaseAlcohol(int $age): bool
    {
        return $age >= self::ALCOHOL_MINIMUM_AGE;
    }
}
```

> [!NOTE]
> **Usa patrones cuando la complejidad lo justifique**: Los patrones de diseño están creados para manejar requisitos cambiantes y una alta complejidad. Si la lógica de tu estado solo tiene una condición simple que nunca cambiará, un patrón de diseño es una forma de sobreingeniería.

---

<a id="premature-optimization"></a>
## Optimización prematura

La **Optimización prematura** consiste en optimizar el código para mejorar el rendimiento antes de tener pruebas concretas (a través de herramientas de perfilado como Xdebug o Blackfire) de que es realmente un cuello de botella.

Esto conduce a un código complejo e ilegible escrito para ahorrar microsegundos, mientras se ignoran las consultas a la base de datos o las solicitudes lentas a APIs externas que tardan cientos de milisegundos.

### La forma incorrecta: Microoptimizaciones ofuscadas

Un desarrollador evita el uso de funciones claras de PHP y utiliza búsquedas de cadenas anidadas y operaciones a nivel de bits para analizar cadenas de configuración, pensando que es más rápido.

```php
// app/Config/Parser.php
declare(strict_types=1);

namespace App\Config;

class Parser
{
    // Cryptic string manipulation to save CPU cycles
    public function parse(string $data): array
    {
        $pos = strpos($data, ':');
        if ($pos === false) return [];
        
        $key = substr($data, 0, $pos);
        $val = substr($data, $pos + 1);
        
        // Bitwise flag check representing boolean configuration
        $isFlagged = (int)$val & 1; 
        
        return [$key => (bool)$isFlagged];
    }
}
```

### La forma correcta: Priorizar el código legible

Escribe código que sea fácil de leer, probar y mantener. Si el rendimiento se convierte en un problema, realiza un perfilado primero, luego optimiza allí donde estén los cuellos de botella.

```php
// app/Config/Parser.php
declare(strict_types=1);

namespace App\Config;

class Parser
{
    public function parse(string $jsonString): array
    {
        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return $decoded;
    }
}
```

---

<a id="cargo-cult"></a>
## Cargo Cult y programación de copiar y pegar

La **Programación de culto al cargo** (Cargo Cult Programming) es la práctica de copiar patrones, estructuras o metodologías de código sin comprender *por qué* se usan o qué problema resuelven.

Un ejemplo clásico en PHP es envolver los modelos Eloquent de Laravel en una capa de repositorio vacía porque "una buena arquitectura requiere repositorios", a pesar de que Eloquent ya implementa los patrones Active Record y Query Builder.

### La forma incorrecta: Envoltorio (wrapper) vacío de abstracción de repositorio

Este repositorio envoltorio simplemente copia los métodos de Eloquent, agregando cero abstracción o valor, al tiempo que introduce el doble de clases para mantener.

```php
// app/Repositories/PostRepositoryInterface.php
declare(strict_types=1);

namespace App\Repositories;

interface PostRepositoryInterface {
    public function find(int $id);
    public function create(array $data);
}

// app/Repositories/EloquentPostRepository.php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Post;

class EloquentPostRepository implements PostRepositoryInterface
{
    public function find(int $id)
    {
        return Post::find($id);
    }

    public function create(array $data)
    {
        return Post::create($data);
    }
}
```

### La forma correcta: Usar Active Record directamente o crear abstracciones reales

Si tu repositorio no aísla al cliente del mecanismo de persistencia (por ejemplo, si devuelve consultas o modelos Eloquent sin procesar de todos modos), deséchalo y utiliza Eloquent directamente.

```php
// app/Http/Controllers/PostController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController
{
    public function show(int $id): JsonResponse
    {
        // Use active record pattern directly
        $post = Post::findOrFail($id);
        return response()->json($post);
    }
}
```

> [!TIP]
> **Cuándo usar repositorios**: Úsalos cuando estés implementando una lógica de dominio compleja que deba permanecer independiente del ORM (por ejemplo, en Diseño Guiado por el Dominio o DDD), o cuando estés creando consultas personalizadas reutilizables que desees probar de forma aislada.

---

<a id="common-mistakes"></a>
## Errores comunes

1. **Forzar patrones en todas partes**: Implementar patrones de diseño en proyectos CRUD simples.
2. **Escribir clases Dios (God Classes)**: Permitir que una clase helper o manager crezca infinitamente en lugar de dividirla en servicios pequeños y cohesivos.
3. **Optimizar sin perfilar**: Reescribir bucles PHP limpios en algoritmos complejos porque "sospechas" que son lentos, mientras la base de datos ejecuta consultas sin índices.
4. **Copia ciega**: Copiar patrones empresariales avanzados (como CQRS, Event Sourcing o capas de repositorio) en proyectos MVC simples solo porque son populares en internet.

---

<a id="checklist"></a>
## Lista de verificación

1. **Comprobación de SRP:** ¿Tiene tu clase más de una razón para cambiar? Si es así, divídela.
2. **Principio KISS:** ¿Puede una condición `if` simple o una función helper reemplazar una jerarquía compleja de POO?
3. **Perfilado de rendimiento:** ¿Has perfilado tu aplicación usando Xdebug o Blackfire antes de aplicar microoptimizaciones?
4. **Abstracción con valor añadido:** ¿Tu repositorio o capa de abstracción realmente oculta los detalles de implementación, o es solo un envoltorio redundante?

---

<a id="summary"></a>
## Resumen

Los antipatrones de diseño son errores comunes que conducen a un software rígido y frágil. El **Código Spaghetti** surge de la falta de separación de conceptos. Los **God Objects** consolidan demasiadas responsabilidades. El **Golden Hammer** impone soluciones incorrectas a los problemas. La **Optimización prematura** prioriza el rendimiento a pequeña escala sobre la legibilidad. La **Programación de culto al cargo (Cargo Culting)** duplica estructuras sin comprender su valor arquitectónico. Mantén el código simple, claro y solo añade patrones de diseño cuando la complejidad lo exija.

---

<a id="self-test-quiz"></a>
## Prueba de autoevaluación

### Pregunta 1: ¿Qué principio SOLID se viola directamente cuando una clase actúa como un "God Object"?
- A) Principio de Abierto/Cerrado (OCP)
- B) Principio de Sustitución de Liskov (LSP)
- C) Principio de Responsabilidad Única (SRP)

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: C**
Un God Object se define por tener múltiples responsabilidades (consultas a la base de datos, integraciones de pago, envíos de correo, plantillas, formateo), violando el Principio de Responsabilidad Única, el cual establece que una clase debe tener una sola razón para cambiar.
</details>

### Pregunta 2: ¿Por qué se considera la optimización prematura como un antipatrón?
- A) Porque los motores de PHP no admiten código optimizado.
- B) Porque incrementa la complejidad del código y reduce la legibilidad antes de que los cuellos de botella de rendimiento hayan sido demostrados y analizados.
- C) Porque las optimizaciones del compilador en PHP 8.x se desactivan automáticamente al optimizar manualmente.

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: B**
La optimización prematura conduce a bloques de código complejos y difíciles de mantener en áreas de la aplicación que rara vez son cuellos de botella. Los verdaderos cuellos de botella casi siempre son las consultas a bases de datos, E/S de archivos o llamadas de red, no las concatenaciones de cadenas.
</details>

### Pregunta 3: ¿Cuál es la característica principal de la programación de culto al cargo (Cargo Cult)?
- A) Copiar a ciegas estructuras de código, patrones de diseño o capas sin comprender su propósito arquitectónico o utilidad real en el proyecto actual.
- B) Migrar código heredado a servicios en la nube como AWS o Docker.
- C) Escribir pruebas después de desplegar el código de producción.

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: A**
La programación de culto al cargo ocurre cuando los desarrolladores copian patrones o capas de arquitectura (como repositorios vacíos, interfaces de servicio o fábricas abstractas) simplemente porque "es la práctica estándar" o "el tutorial lo decía", sin evaluar si resuelve un problema real en su contexto.
</details>
