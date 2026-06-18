---
title: "Patrones Estructurales GoF: Adapter, Decorator, Facade y Proxy | DevSense"
description: "Una guía detallada de los patrones estructurales de diseño GoF en PHP: Adapter, Bridge, Composite, Decorator, Facade, Flyweight y Proxy con ejemplos limpios de código PHP."
published: 2026-06-18
faq:
  - question: "¿Cuál es la diferencia clave entre los patrones Adapter y Decorator?"
    answer: "Un Adapter cambia la interfaz de un objeto para hacerla compatible con otra interfaz, permitiendo que clases incompatibles colaboren. Un Decorator mantiene la interfaz original del objeto envuelto mientras le añade o extiende dinámicamente sus comportamientos."
  - question: "¿En qué se diferencia el patrón Facade del patrón Mediator?"
    answer: "Un Facade proporciona una interfaz simplificada y unidireccional para un subsistema complejo sin restringir el acceso directo a sus clases. Un Mediator centraliza y coordina una comunicación bidireccional compleja entre múltiples objetos colegas, ocultando sus relaciones directas."
  - question: "¿Cuándo se debe usar un Proxy en lugar de un Decorator?"
    answer: "Use un Proxy cuando necesite controlar el acceso a un objeto (por ejemplo, inicialización perezosa, almacenamiento en caché, control de acceso) donde el propio proxy gestiona el ciclo de vida del objeto real. Use un Decorator cuando desee añadir responsabilidades a un objeto dinámicamente en tiempo de ejecución sin gestionar su ciclo de vida de instanciación."
---

# Patrones Estructurales GoF: Adapter, Decorator, Facade y Proxy

Los patrones de diseño estructurales explican cómo ensamblar objetos y clases en estructuras más grandes, manteniendo estas estructuras flexibles y eficientes. Ayudan a construir jerarquías de clases complejas mediante el uso de la composición en lugar de la herencia rígida.

**Guías relacionadas:** [De la arquitectura monolítica a la de microservicios](monolith-to-microservices-architecture) · [Ataques web y prevención](web-attacks-and-prevention)

## Contenido

* [Introducción a los Patrones Estructurales](#intro)
* [El Patrón Adapter (Adaptador)](#adapter)
* [El Patrón Decorator (Decorador)](#decorator)
* [El Patrón Facade (Fachada)](#facade)
* [El Patrón Proxy (Proxy)](#proxy)
* [Otros Patrones Estructurales (Bridge, Composite, Flyweight)](#others)
* [Errores Comunes](#common-mistakes)
* [Lista de Verificación](#checklist)
* [Resumen](#summary)
* [Cuestionario de Autoevaluación](#self-test-quiz)

---

<a id="intro"></a>
## Introducción a los Patrones Estructurales

La herencia es una herramienta poderosa, pero es estática y se resuelve en tiempo de compilación. Los patrones estructurales utilizan la **composición de objetos** para combinar comportamientos dinámicamente en tiempo de ejecución. Esto proporciona una mayor flexibilidad y evita jerarquías de clases profundas y rígidas que son difíciles de mantener.

---

<a id="adapter"></a>
## El Patrón Adapter (Adaptador)

El patrón **Adapter** permite que objetos con interfaces incompatibles colaboren. Actúa como un traductor (envoltorio) que convierte las llamadas del código del cliente en un formato compatible con un subsistema heredado o de terceros.

```php
// app/Logging/LoggerInterface.php
declare(strict_types=1);

namespace App\Logging;

interface LoggerInterface
{
    public function logMessage(string $message): void;
}

// Legacy third-party service that cannot be changed directly
class LegacyLoggerService
{
    public function sendRawLog(string $rawMessage, int $level): void
    {
        // Legacy system logging...
    }
}

// The Adapter class that bridges the two interfaces
class LoggerAdapter implements LoggerInterface
{
    private LegacyLoggerService $legacyLogger;

    public function __construct(LegacyLoggerService $legacyLogger)
    {
        $this->legacyLogger = $legacyLogger;
    }

    public function logMessage(string $message): void
    {
        // Map the call to the legacy service method
        $this->legacyLogger->sendRawLog($message, 1);
    }
}
```

---

<a id="decorator"></a>
## El Patrón Decorator (Decorador)

El patrón **Decorator** añade dinámicamente nuevos comportamientos a los objetos colocándolos dentro de objetos envoltorios especiales que contienen estos comportamientos.

```php
// app/Repository/ProductRepositoryInterface.php
declare(strict_types=1);

namespace App\Repository;

interface ProductRepositoryInterface
{
    public function findById(int $id): array;
}

class SqlProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): array
    {
        // Fetch product from SQL database...
        return ["id" => $id, "name" => "Core PHP Book"];
    }
}

// Decorator implementing the same interface
class CachingProductRepositoryDecorator implements ProductRepositoryInterface
{
    private ProductRepositoryInterface $wrapped;
    private array $cache = [];

    public function __construct(ProductRepositoryInterface $wrapped)
    {
        $this->wrapped = $wrapped;
    }

    public function findById(int $id): array
    {
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = $this->wrapped->findById($id);
        }

        return $this->cache[$id];
    }
}
```

---

<a id="facade"></a>
## El Patrón Facade (Fachada)

El patrón **Facade** proporciona una interfaz simplificada para una biblioteca, un framework o un conjunto complejo de clases.

```php
// app/Shop/OrderFacade.php
declare(strict_types=1);

namespace App\Shop;

class InventoryService { public function check(int $itemId): bool { return true; } }
class PaymentService { public function charge(int $userId, float $amount): bool { return true; } }
class DeliveryService { public function schedule(int $itemId, int $userId): void {} }

class OrderFacade
{
    private InventoryService $inventory;
    private PaymentService $payment;
    private DeliveryService $delivery;

    public function __construct(
        InventoryService $inventory,
        PaymentService $payment,
        DeliveryService $delivery
    ) {
        $this->inventory = $inventory;
        $this->payment = $payment;
        $this->delivery = $delivery;
    }

    public function placeOrder(int $userId, int $itemId, float $price): bool
    {
        if (!$this->inventory->check($itemId)) {
            return false;
        }

        if (!$this->payment->charge($userId, $price)) {
            return false;
        }

        $this->delivery->schedule($itemId, $userId);
        return true;
    }
}
```

---

<a id="proxy"></a>
## El Patrón Proxy (Proxy)

El patrón **Proxy** proporciona un objeto sustituto o intermediario para controlar el acceso al objeto real. Puede manejar inicialización perezosa, control de acceso, almacenamiento en caché o registro (logging).

```php
// app/Http/HeavyReportInterface.php
declare(strict_types=1);

namespace App\Http;

interface HeavyReportInterface
{
    public function render(): string;
}

class RealHeavyReport implements HeavyReportInterface
{
    public function __construct()
    {
        // Simulating heavy database computation
        usleep(50000);
    }

    public function render(): string
    {
        return "<h1>Heavy PDF Report Output</h1>";
    }
}

// Caching & Lazy Initialization Proxy
class HeavyReportProxy implements HeavyReportInterface
{
    private ?RealHeavyReport $realReport = null;
    private ?string $cachedHtml = null;

    public function render(): string
    {
        if ($this->cachedHtml === null) {
            if ($this->realReport === null) {
                // Instantiated only when requested
                $this->realReport = new RealHeavyReport();
            }
            $this->cachedHtml = $this->realReport->render();
        }

        return $this->cachedHtml;
    }
}
```

---

<a id="others"></a>
## Otros Patrones Estructurales

- **Bridge**: Divide una clase grande o un conjunto de clases estrechamente relacionadas en dos jerarquías separadas (abstracción e implementación) que pueden desarrollarse de forma independiente.
- **Composite**: Permite componer objetos en estructuras de árbol para representar jerarquías de parte-todo y trabajar con ellas como si fueran objetos individuales.
- **Flyweight**: Permite encajar más objetos en la memoria RAM disponible compartiendo partes comunes del estado entre varios objetos en lugar de mantener todos los datos en cada objeto.

---

<a id="common-mistakes"></a>
## Errores Comunes

1. **Crear una Fachada Divina**: Colocar lógica de negocio real dentro de la clase Fachada en lugar de delegar en los subsistemas. La Fachada solo debe coordinar los pasos.
2. **Confundir Decorator con Adapter**: Intentar adaptar una interfaz usando un Decorador, o añadir cambios dinámicos de comportamiento usando un Adaptador.
3. **Pilas de Decoradores Profundas**: Apilar demasiados Decoradores encima de un solo objeto, lo que dificulta la depuración de las trazas de pila.

---

<a id="checklist"></a>
## Lista de Verificación

1. **Interfaces:** ¿Sus clases Adapter y Decorator implementan estrictamente interfaces estándar para mantener el polimorfismo?
2. **Desacoplamiento:** ¿Está el cliente de su Fachada desacoplado de las clases internas del subsistema complejo?
3. **Ciclo de vida:** ¿Su Proxy gestiona correctamente el ciclo de vida del sujeto real (creación y limpieza)?
4. **Compatibilidad:** ¿El Adapter convierte los parámetros de manera precisa sin alterar el comportamiento central?

---

<a id="summary"></a>
## Resumen

Los patrones de diseño estructural utilizan la composición de objetos para construir sistemas robustos y mantenibles. Utilice **Adapter** para envolver interfaces incompatibles. Utilice **Decorator** para añadir responsabilidades a los objetos dinámicamente sin crear subclases. Utilice **Facade** para proporcionar una entrada simplificada a bibliotecas complejas. Utilice **Proxy** para gestionar la carga perezosa, la seguridad o el almacenamiento en caché.

---

<a id="self-test-quiz"></a>
## Cuestionario de Autoevaluación

### Pregunta 1: ¿Cuál es la principal ventaja de diseño al utilizar el patrón Decorator en lugar de la herencia?
- A) Compila el código más rápido y se ejecuta a mayor velocidad.
- B) Le permite añadir o modificar comportamientos dinámicamente en tiempo de ejecución, evitando la explosión de combinaciones de subclases en tiempo de compilación.
- C) Garantiza la seguridad de hilos (thread safety) en PHP.

<details>
<summary><b>Mostrar respuesta</b></summary>

**Respuesta: B**
La herencia es estática. Si desea añadir caché y registro a un repositorio, la herencia le obliga a crear clases separadas (por ejemplo, `CachedProductRepository`, `LoggedProductRepository`, `CachedAndLoggedProductRepository`). Los Decoradores le permiten envolverlos dinámicamente: `new Caching(new Logging(new SqlRepository()))`.
</details>

### Pregunta 2: En un patrón Proxy, ¿cuál es la relación entre la clase Proxy y la clase de Sujeto Real?
- A) El Proxy debe extender la clase del Sujeto Real.
- B) Tanto el Proxy como el Sujet Real deben implementar la misma interfaz, permitiendo que el Proxy actúe como un sustituto.
- C) La clase Proxy no puede acceder directamente a los métodos de la clase del Sujeto Real.

<details>
<summary><b>Mostrar respuesta</b></summary>

**Respuesta: B**
Para permitir que los clientes utilicen el Proxy indistintamente con el Sujeto Real, ambos deben implementar la misma interfaz. Esto hace que el proxy sea transparente para el código del cliente.
</details>
