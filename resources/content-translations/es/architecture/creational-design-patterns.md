---
title: "Patrones de Creación GoF: Singleton, Factory, Builder y Prototype | DevSense"
description: "Un análisis profundo de los patrones de creación de GoF en PHP: Singleton, Factory Method, Abstract Factory, Builder y Prototype con tipos estrictos y ejemplos reales."
published: 2026-06-18
faq:
  - question: "¿Por qué el patrón Singleton se considera a menudo un antipatrón?"
    answer: "El Singleton se considera un antipatrón porque introduce un estado global en la aplicación, acopla fuertemente las clases a un recurso estático, dificulta las pruebas unitarias (debido a la persistencia del estado entre pruebas) y viola el principio de responsabilidad única."
  - question: "¿Cuándo se debe usar el patrón Builder en lugar de una Factory?"
    answer: "Utilice el patrón Builder cuando la construcción de un objeto involucre un gran número de parámetros, una configuración compleja paso a paso o pasos opcionales. Las Factories son más adecuadas para instanciaciones de un solo paso."
  - question: "¿Cuál es la diferencia entre Factory Method y Abstract Factory?"
    answer: "Factory Method define una interfaz para crear un único objeto, delegando la instanciación a las subclases. Abstract Factory proporciona una interfaz para crear familias de objetos relacionados o dependientes sin especificar sus clases concretas."
---

# Patrones de Creación GoF: Singleton, Factory, Builder y Prototype

Los patrones de creación (creational design patterns) abstraen el proceso de instanciación. Hacen que un sistema sea independiente de cómo se crean, componen y representan sus objetos. Al encapsular el conocimiento sobre las clases concretas que utiliza el sistema, ocultan los detalles de cómo se crean y ensamblan estas instancias.

**Guías relacionadas:** [De la arquitectura monolítica a la de microservicios](monolith-to-microservices-architecture) · [Pooling de conexiones de base de datos en PHP](php-database-connection-pooling)

## Contenido

* [Introducción a los patrones de creación](#intro)
* [El patrón Singleton](#singleton)
* [Factory Method y Abstract Factory](#factory)
* [El patrón Builder](#builder)
* [El patrón Prototype](#prototype)
* [Errores comunes](#common-mistakes)
* [Lista de verificación](#checklist)
* [Resumen](#summary)
* [Prueba de autoevaluación](#self-test-quiz)

---

<a id="intro"></a>
## Introducción a los patrones de creación

La creación de objetos directamente con el operador `new` puede sobrecargar la lógica de negocio e introduce un acoplamiento fuerte. Los patrones de creación desacoplan el código cliente de los detalles concretos de instanciación.

En el desarrollo moderno de PHP, el ciclo de vida de los objetos a menudo se gestiona mediante contenedores de inyección de dependencias (DI), pero comprender los patrones de creación es fundamental para escribir SDKs personalizados, bibliotecas y objetos de dominio complejos.

---

<a id="singleton"></a>
## El patrón Singleton

El **Singleton** garantiza que una clase tenga una única instancia y proporciona un punto de acceso global a ella.

```php
// app/Database/DatabaseConnection.php
declare(strict_types=1);

namespace App\Database;

use RuntimeException;

class DatabaseConnection
{
    private static ?self $instance = null;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            if (empty($config)) {
                throw new RuntimeException("Database connection must be initialized with configuration.");
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    // Prevenir clonación y deserialización
    private function __clone() {}
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize a singleton class.");
    }

    public function query(string $sql): array
    {
        // Ejecución de consulta SQL...
        return ["status" => "success", "sql" => $sql];
    }
}
```

> [!WARNING]
> **Advertencia para pruebas unitarias**: Los Singletons conservan su estado entre las pruebas unitarias. Si no restablece la propiedad estática `$instance` entre pruebas, se romperá el aislamiento de las mismas.

---

<a id="factory"></a>
## Factory Method y Abstract Factory

### Factory Method
Factory Method define una interfaz para crear un objeto, pero deja que las subclases decidan qué clase instanciar.

```php
// app/Payments/PaymentGatewayFactory.php
declare(strict_types=1);

namespace App\Payments;

interface PaymentGateway
{
    public function charge(float $amount): bool;
}

class StripeGateway implements PaymentGateway
{
    public function charge(float $amount): bool
    {
        // Lógica de integración con Stripe...
        return true;
    }
}

abstract class PaymentProcessor
{
    abstract protected function createGateway(): PaymentGateway;

    public function process(float $amount): bool
    {
        $gateway = $this->createGateway();
        return $gateway->charge($amount);
    }
}

class StripeProcessor extends PaymentProcessor
{
    protected function createGateway(): PaymentGateway
    {
        return new StripeGateway();
    }
}
```

### Abstract Factory
Abstract Factory proporciona una interfaz para crear familias de objetos relacionados o dependientes.

```php
// app/UI/WidgetFactory.php
declare(strict_types=1);

namespace App\UI;

interface Button { public function render(): string; }
interface Input { public function render(): string; }

class DarkButton implements Button { public function render(): string { return "<button class='dark'>Submit</button>"; } }
class DarkInput implements Input { public function render(): string { return "<input class='dark' />"; } }

interface WidgetFactory
{
    public function createButton(): Button;
    public function createInput(): Input;
}

class DarkThemeFactory implements WidgetFactory
{
    public function createButton(): Button { return new DarkButton(); }
    public function createInput(): Input { return new DarkInput(); }
}
```

---

<a id="builder"></a>
## El patrón Builder

El patrón **Builder** separa la construcción de un objeto complejo de su representación, permitiendo que el mismo proceso de construcción cree representaciones diferentes.

```php
// app/Http/RequestBuilder.php
declare(strict_types=1);

namespace App\Http;

class Request
{
    public string $url = '';
    public string $method = 'GET';
    public array $headers = [];
    public string $body = '';
}

class RequestBuilder
{
    private Request $request;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): self
    {
        $this->request = new Request();
        return $this;
    }

    public function url(string $url): self
    {
        $this->request->url = $url;
        return $this;
    }

    public function method(string $method): self
    {
        $this->request->method = strtoupper($method);
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->request->headers[$name] = $value;
        return $this;
    }

    public function body(string $body): self
    {
        $this->request->body = $body;
        return $this;
    }

    public function build(): Request
    {
        $result = $this->request;
        $this->reset();
        return $result;
    }
}
```

---

<a id="prototype"></a>
## El patrón Prototype

El patrón **Prototype** especifica los tipos de objetos a crear utilizando una instancia prototípica, y crea nuevos objetos copiando este prototipo.

```php
// app/Marketing/CampaignTemplate.php
declare(strict_types=1);

namespace App\Marketing;

use DateTime;

class CampaignTemplate
{
    public string $title;
    public string $content;
    public DateTime $scheduledAt;

    public function __construct(string $title, string $content)
    {
        $this->title = $title;
        $this->content = $content;
        $this->scheduledAt = new DateTime();
    }

    public function __clone()
    {
        // Forzar la clonación del objeto DateTime anidado
        $this->scheduledAt = new DateTime();
        $this->title = "Copia de " . $this->title;
    }
}
```

---

<a id="common-mistakes"></a>
## Errores comunes

1. **Uso excesivo de Singleton**: Tratar los Singletons como variables globales. Esto acopla el código, arruina el aislamiento de las pruebas y oculta dependencias.
2. **Sobreingeniería de Factories**: Crear fábricas para objetos que pueden instanciarse directamente mediante un constructor cuando no existen variantes de familias.
3. **Clonación superficial en Prototype**: Olvidar clonar profundamente las propiedades de tipo objeto, provocando que el clon comparta memoria con el prototipo original.

---

<a id="checklist"></a>
## Lista de verificación

1. **Encapsulación:** ¿Hizo privado el constructor, privado el método clone y obligó al método wakeup a lanzar una excepción en su Singleton?
2. **Complejidad:** ¿La configuración de su objeto requiere una configuración paso a paso u opcional? Si es así, use un Builder.
3. **Clonación:** ¿Se aseguró de realizar una clonación profunda en su clase Prototype si contiene objetos anidados?
4. **Desacoplamiento:** ¿Está utilizando interfaces para los productos creados por sus Factories?

---

<a id="summary"></a>
## Resumen

Los patrones de creación le ayudan a separar la instanciación de objetos de la lógica de la aplicación. Utilice **Singleton** solo cuando deba garantizar que exista exactamente una instancia. Utilice **Factories** para desacoplar el código cliente de las implementaciones de subclases concretas. Utilice **Builder** para el ensamblaje paso a paso de objetos complejos. Utilice **Prototype** cuando la creación de instancias sea costosa en recursos y clonar una existente sea más eficiente.

---

<a id="self-test-quiz"></a>
## Prueba de autoevaluación

### Pregunta 1: ¿Cuál es el principal riesgo estructural de hacer referencia a una clase Singleton directamente dentro de su lógica de negocio?
- A) PHP fallará debido al agotamiento de la memoria de ejecución.
- B) Introduce un acoplamiento fuerte y un estado global oculto, lo que hace que el código sea extremadamente difícil de probar unitariamente en aislamiento.
- C) Desencadena un error de compilación fatal en PHP 8.0+.

<details>
<summary><b>Mostrar respuesta</b></summary>

**Respuesta: B**
Hacer referencia a Singletons directamente acopla su clase a un estado global. En las pruebas unitarias, no puede simular esa conexión, y el estado puede filtrarse de una prueba a otra, produciendo pruebas inestables.
</details>

### Pregunta 2: En PHP, ¿qué ocurre durante la clonación de objetos (`$b = clone $a`) si una clase contiene propiedades de referencia (como otros objetos) y no define un método `__clone()` personalizado?
- A) PHP clona automáticamente todos los objetos anidados de forma recursiva.
- B) PHP realiza una copia superficial, lo que significa que las propiedades de referencia en `$b` apuntarán a las mismas instancias de objeto que `$a`.
- C) Se lanza un error de tiempo de ejecución inmediatamente.

<details>
<summary><b>Mostrar respuesta</b></summary>

**Respuesta: B**
La clonación predeterminada de PHP realiza una copia superficial. Cualquier propiedad de objeto anidado conservará su referencia original, por lo que modificar un objeto anidado en la instancia clonada afectará al prototipo original.
</details>
