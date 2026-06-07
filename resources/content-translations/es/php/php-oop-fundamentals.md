---
title: "Fundamentos de OOP en PHP: De conceptos clave a visibilidad asimétrica | DevSense"
description: "Domine la Programación Orientada a Objetos moderna en PHP. Aprenda encapsulamiento, herencia, interfaces, clases abstractas, traits, clases anónimas (7.0+), propiedades readonly (8.1+), clases readonly (8.2+) y visibilidad asimétrica (8.4+)."
faq:
  - question: "¿Cuál es la diferencia entre una interfaz y una clase abstracta en PHP?"
    answer: "Una interfaz define un contrato público puro que las clases que la implementan deben cumplir, permitiendo múltiples implementaciones. Una clase abstracta es una clase parcialmente implementada que puede contener estado, lógica de constructor y métodos protegidos, pero dado que PHP solo admite herencia simple, una clase solo puede extender una clase abstracta."
  - question: "¿Cómo funcionan las propiedades readonly en PHP 8.1+?"
    answer: "Las propiedades readonly solo se pueden inicializar una vez, normalmente dentro del constructor de la clase. Cualquier intento posterior de modificar o eliminar (unset) la propiedad lanzará una excepción Error, garantizando la inmutabilidad."
  - question: "¿Qué es la visibilidad asimétrica en PHP 8.4+?"
    answer: "La visibilidad asimétrica permite declarar diferentes niveles de acceso para leer y escribir una propiedad. Por ejemplo, 'public private(set) string $name' permite el acceso público de lectura pero restringe el acceso de escritura a la propia clase, eliminando el código repetitivo de los getters."
  - question: "¿Cómo se resuelven las colisiones de nombres de traits en PHP?"
    answer: "Cuando dos traits definen un método con el mismo nombre, PHP lanza un error fatal a menos que resuelva explícitamente la colisión utilizando el operador 'insteadof' para elegir un método, o el operador 'as' para definir un alias para el método bajo un nuevo nombre."
---

# Fundamentos de OOP en PHP: Proteger el estado y definir interfaces limpias

Si alguna vez ha pasado horas depurando una misteriosa mutación de estado en una gran aplicación web, sabrá cuán frágiles pueden ser los objetos mal encapsulados. Un servicio cambia una propiedad pública en una instancia compartida y, de repente, las entradas de la base de datos en todo el sistema se corrompen. El problema principal no es la Programación Orientada a Objetos (OOP) en sí, sino nuestra incapacidad para imponer límites estrictos entre nuestros objetos.

En el código procedimental, los datos se pasan de forma global, dejándolos vulnerables a modificaciones. In una OOP ingenua, las clases se tratan como meras bolsas de propiedades, exponiendo todo a través de variables públicas o getters y setters genéricos. El PHP moderno resuelve estos problemas de arquitectura al pasar de objetos abiertos y mutables a estructuras estrictas y autónomas.

> [!IMPORTANT]
> La OOP moderna en PHP no se trata solo de organizar funciones en clases; se trata de imponer límites de estado fuertes, definir contratos públicos estrictos y utilizar la seguridad de tipos para evitar estados de ejecución no válidos.

**Nivel objetivo**: Junior / Middle

---

## Índice
* [Encapsulamiento y modificadores de acceso](#encapsulation-modifiers)
* [Contratos y abstracción: Herencia, clases abstractas e interfaces](#contracts-abstraction)
* [Reutilización horizontal y comportamiento dinámico: Traits y clases anónimas (PHP 7.0+)](#horizontal-reuse)
* [Inmutabilidad por defecto: Propiedades readonly (PHP 8.1+) y clases readonly (PHP 8.2+)](#immutability)
* [Límites detallados: Visibilidad asimétrica (PHP 8.4+)](#asymmetric-visibility)
* [Compromisos arquitectónicos y limitaciones](#trade-offs)
* [🧠 Preguntas de autoevaluación](#self-check)

---

<a id="encapsulation-modifiers"></a>
## Encapsulamiento y modificadores de acceso

### 1. Punto clave
El encapsulamiento es la práctica de ocultar el estado interno de un objeto y requerir que todas las interacciones pasen a través de una interfaz pública. PHP controla este acceso utilizando tres modificadores:
* `public`: Accesible desde cualquier lugar.
* `protected`: Accesible solo dentro de la propia clase y por clases herederas.
* `private`: Accesible solo dentro de la clase que lo define.

### 2. Por qué es importante
Sin encapsulamiento, los servicios externos pueden modificar el estado interno de un objeto sin su conocimiento, eludiendo las reglas de validación. Por ejemplo, si el saldo de una cuenta bancaria (`BankAccount`) se puede escribir directamente desde el exterior, no podemos garantizar que el saldo nunca caiga por debajo de cero o que las transacciones se registren correctamente.

### 3. Ejemplo
Veamos cómo la exposición de propiedades públicas conduce a la corrupción del estado y cómo los modificadores privados imponen invariantes:

```php
// app/Finance/BankAccount.php
namespace App\Finance;

use InvalidArgumentException;

class BankAccount
{
    // Private properties prevent direct external modification
    private float $balance = 0.0;
    private array $ledger = [];

    public function __construct(float $initialDeposit)
    {
        if ($initialDeposit < 0) {
            throw new InvalidArgumentException("Initial deposit cannot be negative.");
        }
        $this->balance = $initialDeposit;
        $this->ledger[] = "Account opened with: {$initialDeposit}";
    }

    // Public method provides controlled access to mutate state
    public function deposit(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Deposit amount must be positive.");
        }
        $this->balance += $amount;
        $this->ledger[] = "Deposited: {$amount}";
    }

    public function withdraw(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Withdrawal amount must be positive.");
        }
        if ($amount > $this->balance) {
            throw new InvalidArgumentException("Insufficient funds.");
        }
        $this->balance -= $amount;
        $this->ledger[] = "Withdrew: {$amount}";
    }

    // Getter provides read-only access without exposing mutable references
    public function getBalance(): float
    {
        return $this->balance;
    }
}
```

### 4. Consecuencia
Al declarar el `$balance` como privado, la clase conserva el control total sobre su validación. El código externo no puede ejecutar `$account->balance = -500.0;`. Las transiciones de estado solo pueden ocurrir a través de operaciones comerciales válidas (`deposit` y `withdraw`), lo que garantiza que el libro de contabilidad (ledger) y el saldo estén siempre sincronizados.

---

<a id="contracts-abstraction"></a>
## Contratos y abstracción: Herencia, clases abstractas e interfaces

### 1. Punto clave
PHP permite a los desarrolladores diseñar comportamiento reutilizable y límites arquitectónicos estrictos mediante herencia y polimorfismo:
* **Herencia (`extends`)**: Permitir que una clase hija herede propiedades y métodos de una clase padre. Las clases hijas pueden sobrescribir los métodos del padre a menos que el método o la clase padre estén marcados como `final`.
* **Clases abstractas (`abstract class`)**: Plantillas que no se pueden instanciar por sí mismas. Pueden contener métodos completamente implementados, así como firmas de métodos `abstract` que los hijos deben implementar.
* **Interfaces (`interface`)**: Contratos puros que contienen solo firmas de métodos sin implementación. Una clase puede implementar múltiples interfaces, resolviendo la limitación de herencia única de PHP.

### 2. Por qué es importante
La herencia le permite compartir lógica común, pero crea un acoplamiento fuerte (tight coupling) entre el padre y el hijo. Las clases abstractas actúan como un término medio, ofreciendo un diseño parcial. Las interfaces representan el nivel más alto de desacoplamiento: definen *qué* puede hacer un objeto, no *cómo* lo hace. Esto le permite intercambiar adaptadores de base de datos, controladores de correo o pasarelas de pago sin cambiar la lógica de la aplicación que depende de ellos.

### 3. Ejemplo
Así es como construimos un contrato de procesamiento de pagos utilizando una interfaz, una base abstracta e implementaciones concretas:

```php
// app/Payments/PaymentGatewayInterface.php
namespace App\Payments;

use App\Payments\PaymentGatewayInterface;

interface PaymentGatewayInterface
{
    public function charge(int $amountInCents): bool;
}
```

```php
// app/Payments/AbstractPaymentGateway.php
namespace App\Payments;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    protected string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // Shared utility method available to child classes
    protected function logTransaction(string $gateway, int $amount, bool $success): void
    {
        // Imagine writing this to a system log
        echo sprintf("[%s] Charged %d cents. Success: %s\n", $gateway, $amount, $success ? 'Yes' : 'No');
    }
}
```

```php
// app/Payments/StripeGateway.php
namespace App\Payments;

class StripeGateway extends AbstractPaymentGateway
{
    public function charge(int $amountInCents): bool
    {
        // Stripe-specific API implementation
        $success = true; // Call Stripe API using $this->apiKey
        
        $this->logTransaction('Stripe', $amountInCents, $success);
        return $success;
    }
}
```

### 4. Consecuencia
Las clases de alto nivel (como un `CheckoutController`) pueden depender de `PaymentGatewayInterface` en lugar de clases concretas. Podemos intercambiar `StripeGateway` por `PaypalGateway` a través de la inyección de dependencias (Dependency Injection), o simular (mock) la pasarela por completo en las pruebas de PHPUnit, sin alterar la lógica de compra.

---

<a id="horizontal-reuse"></a>
## Reutilización horizontal y comportamiento dinámico: Traits y clases anónimas (PHP 7.0+)

### 1. Punto clave
PHP es un lenguaje de herencia única. Para evitar la duplicación de código sin forzar jerarquías de clases, PHP ofrece dos mecanismos:
* **Traits**: Bloques de código que se pueden insertar en clases para compartir métodos horizontalmente. Si ocurren conflictos de nombres entre dos traits, deben resolverse usando los operadores `insteadof` y `as`.
* **Clases anónimas (PHP 7.0+)**: Clases ligeras y sin nombre declaradas al vuelo. Son útiles para implementaciones simples y temporales de interfaces o clases abstractas.

### 2. Por qué es importante
Forzar jerarquías de clases solo para compartir código (como registro de datos o borrado lógico - soft deletes) conduce a clases padre frágiles y árboles de herencia desordenados. Los traits resuelven esto al permitir importar múltiples comportamientos independientes en una sola clase. Las clases anónimas evitan el código repetitivo cuando necesita simular una clase o proporcionar una implementación de un solo uso para una prueba o devolución de llamada (callback).

### 3. Ejemplo
El siguiente código demuestra un trait con resolución de colisiones y una clase anónima utilizada para el registro en pruebas:

```php
// app/Traits/LoggerTraits.php
namespace App\Traits;

trait LoggerA
{
    public function log(string $message): void
    {
        echo "LoggerA: {$message}\n";
    }
}

trait LoggerB
{
    public function log(string $message): void
    {
        echo "LoggerB: {$message}\n";
    }
}
```

```php
// app/Services/AuditService.php
namespace App\Services;

use App\Traits\LoggerA;
use App\Traits\LoggerB;

class AuditService
{
    use LoggerA, LoggerB {
        // Resolve conflict: Use LoggerA's log method instead of LoggerB's
        LoggerA::log insteadof LoggerB;
        // Keep LoggerB's log method accessible under an alias
        LoggerB::log as logFromB;
    }

    public function performAudit(): void
    {
        $this->log("Auditing system event..."); // Calls LoggerA::log
        $this->logFromB("Backup check...");     // Calls LoggerB::log
    }
}
```

```php
// app/Contracts/MailerInterface.php
namespace App\Contracts;

interface MailerInterface {
    public function send(string $recipient, string $body): void;
}
```

```php
// app/Services/MailerDemo.php
namespace App\Services;

use App\Contracts\MailerInterface;

// Creating a one-time class instance on the fly (PHP 7.0+)
$mockMailer = new class implements MailerInterface {
    public function send(string $recipient, string $body): void
    {
        echo "Mock sent to {$recipient} with body: {$body}\n";
    }
};
```

### 4. Consecuencia
Al usar traits, `AuditService` adquiere comportamientos compartidos de múltiples fuentes sin necesidad de extender una clase base común. Con las clases anónimas, podemos instanciar implementaciones en línea de `MailerInterface` para pruebas rápidas o scripts pequeños, manteniendo el sistema de archivos limpio de clases de un solo uso.

---

<a id="immutability"></a>
## Inmutabilidad por defecto: Propiedades readonly (PHP 8.1+) y clases readonly (PHP 8.2+)

### 1. Punto clave
PHP introduce herramientas modernas para imponer la inmutabilidad del estado a nivel del motor (engine):
* **Propiedades readonly (PHP 8.1+)**: Propiedades en las que solo se puede escribir una vez (normalmente en el constructor). Las modificaciones posteriores o los intentos de eliminarlas mediante `unset()` lanzarán un `Error`. Deben tener tipo; las propiedades sin tipo no se pueden marcar como readonly.
* **Clases readonly (PHP 8.2+)**: Azúcar sintáctico que marca implícitamente todas las propiedades de una clase como `readonly` y evita la creación de propiedades dinámicas.

### 2. Por qué es importante
Los objetos de transferencia de datos (DTOs) y las configuraciones se pasan a través de muchas capas de una aplicación. Si estas estructuras son mutables, cualquier servicio a lo largo del ciclo de vida de la solicitud podría alterar accidentalmente sus valores, lo que provocaría errores silenciosos. Imponer la inmutabilidad a nivel del compilador garantiza que una vez que se construye un DTO, sus datos permanezcan idénticos durante todo el tiempo de ejecución.

### 3. Ejemplo
Veamos cómo imponemos la inmutabilidad en un DTO de usuario:

```php
// app/DTO/UserConfiguration.php
namespace App\DTO;

// PHP 8.2+ allows marking the entire class as readonly
readonly class UserConfiguration
{
    // All properties in a readonly class are implicitly readonly and must be typed
    public string $theme;
    public int $itemsPerPage;

    public function __construct(string $theme, int $itemsPerPage)
    {
        $this->theme = $theme;
        $this->itemsPerPage = $itemsPerPage;
    }
}
```

```php
// app/Services/ConfigConsumer.php
namespace App\Services;

use App\DTO\UserConfiguration;

$config = new UserConfiguration('dark', 25);

// Attempting to modify values:
// $config->theme = 'light'; 
// ❌ Fatal Error: Cannot modify readonly property App\DTO\UserConfiguration::$theme
```

### 4. Consecuencia
Cualquier código que consuma `$config` tiene la garantía de que la configuración del usuario no cambiará a mitad de la solicitud. Esto elimina la necesidad de propiedades privadas repetitivas y métodos de solo lectura (getters), simplificando drásticamente el código DTO.

---

<a id="asymmetric-visibility"></a>
## Límites detallados: Visibilidad asimétrica (PHP 8.4+)

### 1. Punto clave
PHP 8.4+ introduce la **Visibilidad asimétrica**, que permite definir diferentes niveles de acceso para leer y escribir una propiedad en la misma línea.
* Sintaxis: `public private(set) Type $property`
* La visibilidad de lectura (por ejemplo, `public`) se especifica primero.
* La visibilidad de escritura (por ejemplo, `private(set)` o `protected(set)`) se especifica inmediatamente después.

### 2. Por qué es importante
Antes de PHP 8.4, si quería que una propiedad fuera de lectura pública pero solo de escritura dentro de la clase, tenía que hacerla `private` y escribir un método getter público, o hacer que la clase/propiedad fuera `readonly`. Sin embargo, `readonly` evita *cualquier* mutación interna después de la inicialización. La visibilidad asimétrica permite que una clase mute sus propias propiedades internamente mientras las expone como de solo lectura al exterior, sin ningún código repetitivo de getter.

### 3. Ejemplo
Aquí hay una entidad `Product` cuyo precio puede cambiar con el tiempo, pero solo a través de métodos de clase:

```php
// app/Catalog/Product.php
namespace App\Catalog;

use InvalidArgumentException;

class Product
{
    // Publicly readable, but can only be set privately from within this class (PHP 8.4+)
    public private(set) int $priceInCents;
    
    // Publicly readable, but can be set by this class and child classes (protected)
    public protected(set) string $name;

    public function __construct(string $name, int $priceInCents)
    {
        $this->name = $name;
        $this->setPrice($priceInCents);
    }

    public function setPrice(int $newPriceInCents): void
    {
        if ($newPriceInCents < 0) {
            throw new InvalidArgumentException("Price cannot be negative.");
        }
        $this->priceInCents = $newPriceInCents;
    }
}
```

```php
// app/Services/CatalogService.php
namespace App\Services;

use App\Catalog\Product;

$product = new Product('Mechanical Keyboard', 9900);

// Reading the property directly works without a getter
echo $product->priceInCents; // Output: 9900

// Writing directly fails:
// $product->priceInCents = 8500; 
// ❌ Fatal Error: Cannot modify property App\Catalog\Product::$priceInCents from global scope
```

### 4. Consecuencia
Obtenemos la sintaxis limpia de las propiedades públicas para leer valores, al tiempo que mantenemos un encapsulamiento estricto. Ya no necesitamos escribir código repetitivo como `public function getPriceInCents(): int { return $this->priceInCents; }`.

---

<a id="trade-offs"></a>
## Compromisos arquitectónicos y limitaciones

Ningún patrón es una solución mágica. Aplicar características de OOP sin evaluar sus compensaciones conduce a bases de código sobrediseñadas o difíciles de mantener.

### 1. Acoplamiento fuerte por herencia (El problema de la clase base frágil)
Aunque extender clases es fácil, introduce un acoplamiento fuerte. Si una clase padre (`AbstractPaymentGateway`) cambia la firma de su constructor, se debe actualizar cada clase hija. Favorezca la **composición sobre la herencia** inyectando dependencias en lugar de extender clases base.

### 2. La magia oculta de los traits
Los traits pueden enmascarar fácilmente una mala arquitectura. Parecen un copiar y pegar asistido por el compilador, lo que facilita la creación de dependencias ocultas y colisiones de métodos. Si varias clases necesitan la misma lógica, considere crear una clase de soporte (helper) dedicada e inyectarla como una dependencia.

### 3. Limitaciones de las propiedades readonly
Las propiedades readonly (PHP 8.1+) no se pueden restablecer ni modificar, incluso dentro de la clase. Si necesita cambiar el estado internamente (por ejemplo, para rastrear cambios de estado o invalidar el caché), las propiedades readonly no funcionarán. Además, no pueden tener valores por defecto y no se pueden usar con hooks de propiedad (property hooks en PHP 8.4+).

### 4. Restricciones de la visibilidad asimétrica
La visibilidad asimétrica (PHP 8.4+) requiere que la operación de escritura (`set`) sea estrictamente más restringida que la operación de lectura (`get`). Por ejemplo, `private public(set)` no es válido y arrojará un error de sintaxis. Además, las propiedades con visibilidad asimétrica no se pueden pasar por referencia:

```php
// app/Services/ReferenceDemo.php
namespace App\Services;

use App\Catalog\Product;

$product = new Product('Mouse', 2500);

// If we try to pass by reference:
// sort($product->priceInCents); 
// ❌ Fatal Error: Cannot pass property App\Catalog\Product::$priceInCents by reference
```

---

## Consejo práctico: El árbol de decisiones de visibilidad

Al definir propiedades de clase, siga esta regla práctica para mantener su encapsulamiento seguro y el código limpio:

1. **¿Cambia la propiedad después de la instanciación del constructor?**
   * **No**: Use una propiedad `readonly` (PHP 8.1+) o una clase `readonly` (PHP 8.2+).
   * **Sí**: Proceda al paso 2.
2. **¿Debería el código externo poder modificar el valor directamente?**
   * **No**: Use `public private(set)` (PHP 8.4+) para exponer el valor para lectura sin exponer el acceso de escritura.
   * **Sí**: Use una propiedad `public` estándar (tenga en cuenta que esto elude la validación).

---

## 🧠 Preguntas de autoevaluación

1. **¿Por qué PHP lanza un error fatal (Fatal Error) si intenta declarar una propiedad como `private public(set)` en PHP 8.4+?**
2. **¿Verdadero o Falso?** Una clase hija puede sobrescribir un método en la clase padre incluso si el método padre tiene el prefijo de la palabra clave `final`.
3. **¿Qué sucede si intenta eliminar (unset) o reinicializar una propiedad `readonly` después de haber sido establecida?**

<details>
<summary><b>Mostrar respuestas</b></summary>

1. La visibilidad de escritura (`set`) debe ser igual o más estricta que la visibilidad de lectura. Dado que `private(set)` es más estricta que `public`, `public private(set)` es válida. Sin embargo, `public(set)` es más amplia que la visibilidad de lectura `private`, lo cual es ilógico y el analizador sintáctico (parser) lo rechaza.
2. **Falso.** Marcar una clase o método como `final` evita que las clases hijas sobrescriban ese método específico o hereden de la clase.
3. Lanza una excepción `Error`: *Cannot modify readonly property...* o *Cannot unset readonly property...*.
</details>
