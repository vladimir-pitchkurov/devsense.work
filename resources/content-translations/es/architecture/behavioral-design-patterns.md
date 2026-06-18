---
title: "Patrones de Comportamiento GoF: Chain of Responsibility, Observer, Strategy y Command | DevSense"
description: "Una guía detallada de los patrones de comportamiento GoF en PHP: Strategy, Observer, Chain of Responsibility, Command, Iterator, Mediator, Memento, State, Template Method, Visitor y Interpreter con ejemplos limpios de código PHP."
published: 2026-06-18
faq:
  - question: "¿Cuál es la diferencia principal entre los patrones Strategy y State?"
    answer: "El patrón Strategy se utiliza cuando se desea elegir o intercambiar un algoritmo o estrategia de ejecución específica en tiempo de ejecución, y el cliente generalmente conoce las estrategias. El patrón State se utiliza cuando un objeto se comporta de manera diferente según su estado interno, y las transiciones de estado son gestionadas automáticamente por los propios objetos de estado, por lo general ocultas para el cliente."
  - question: "¿En qué se diferencia el patrón Observer del patrón Publish-Subscribe?"
    answer: "En el patrón Observer, el sujeto (publisher) mantiene una lista directa de sus observadores y les notifica directamente. En Publish-Subscribe, existe un intermediario de mensajes o canal de eventos que desacopla por completo a los editores de los suscriptores, de modo que no se conocen entre sí."
  - question: "¿Cuándo se debe usar Chain of Responsibility en lugar de un Decorator?"
    answer: "Use Chain of Responsibility cuando cualquiera de varios objetos manejadores pueda procesar una solicitud (o pasarla al siguiente), y la solicitud pueda detenerse o cortocircuitarse en cualquier etapa. Use un Decorator cuando desee ejecutar todos los envoltorios en una pila para enriquecer el comportamiento del objeto central, sin cortocircuitar ni seleccionar un único manejador."
---

# Patrones de Comportamiento GoF: Chain of Responsibility, Observer, Strategy y Command

Los patrones de comportamiento se centran en los algoritmos y la asignación de responsabilidades entre objetos. No describen únicamente patrones de objetos o clases, sino también los patrones de comunicación entre ellos. Estos patrones caracterizan flujos de control complejos que son difíciles de seguir en tiempo de ejecución.

**Guías relacionadas:** [Patrones Estructurales GoF](structural-design-patterns) · [De la arquitectura monolítica a la de microservicios](monolith-to-microservices-architecture)

## Contenido

* [Introducción a los Patrones de Comportamiento](#intro)
* [El Patrón Strategy (Estrategia)](#strategy)
* [El Patrón Observer (Observador)](#observer)
* [El Patrón Chain of Responsibility (Cadena de responsabilidad)](#chain-of-responsibility)
* [El Patrón Command (Comando)](#command)
* [Otros Patrones de Comportamiento (Iterator, Mediator, Memento, State, Template Method, Visitor, Interpreter)](#others)
* [Errores Comunes](#common-mistakes)
* [Lista de Verificación](#checklist)
* [Resumen](#summary)
* [Cuestionario de Autoevaluación](#self-test-quiz)

---

<a id="intro"></a>
## Introducción a los Patrones de Comportamiento

Mientras que los patrones de creación se centran en la instanciación de objetos y los patrones estructurales en la composición de clases, los **patrones de comportamiento** se concentran en la comunicación. Definen formas limpias de delegar acciones, enrutar solicitudes, gestionar cambios de estado e intercambiar algoritmos dinámicamente durante el tiempo de ejecución.

---

<a id="strategy"></a>
## El Patrón Strategy (Estrategia)

El patrón **Strategy** define una familia de algoritmos, encapsula cada uno de ellos y los hace intercambiables. Strategy permite que el algoritmo varíe independientemente de los clientes que lo utilizan.

```php
// app/Payment/PaymentStrategyInterface.php
declare(strict_types=1);

namespace App\Payment;

interface PaymentStrategyInterface
{
    public function pay(float $amount): bool;
}

class StripePaymentStrategy implements PaymentStrategyInterface
{
    public function pay(float $amount): bool
    {
        // Stripe payment API logic...
        return true;
    }
}

class PayPalPaymentStrategy implements PaymentStrategyInterface
{
    public function pay(float $amount): bool
    {
        // PayPal payment API logic...
        return true;
    }
}

// Client context class
class CheckoutProcessor
{
    private PaymentStrategyInterface $paymentStrategy;

    public function __construct(PaymentStrategyInterface $paymentStrategy)
    {
        $this->paymentStrategy = $paymentStrategy;
    }

    public function setPaymentStrategy(PaymentStrategyInterface $paymentStrategy): void
    {
        $this->paymentStrategy = $paymentStrategy;
    }

    public function executeCheckout(float $amount): bool
    {
        return $this->paymentStrategy->pay($amount);
    }
}
```

---

<a id="observer"></a>
## El Patrón Observer (Observador)

El patrón **Observer** define una dependencia de uno a muchos entre objetos, de modo que cuando un objeto cambia de estado, todos sus dependientes son notificados y actualizados automáticamente.

```php
// app/Events/UserRegisterObserverInterface.php
declare(strict_types=1);

namespace App\Events;

interface UserRegisterObserverInterface
{
    public function update(string $email): void;
}

class SendWelcomeEmailObserver implements UserRegisterObserverInterface
{
    public function update(string $email): void
    {
        // Mailer logic to send welcome email...
    }
}

class CreatePromoCodeObserver implements UserRegisterObserverInterface
{
    public function update(string $email): void
    {
        // Database logic to generate a new user coupon...
    }
}

// Subject class holding references to observers
class UserRegistrationService
{
    private array $observers = [];

    public function attach(UserRegisterObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }

    public function registerUser(string $email): void
    {
        // Create user record in DB...
        
        // Notify observers
        foreach ($this->observers as $observer) {
            $observer->update($email);
        }
    }
}
```

---

<a id="chain-of-responsibility"></a>
## El Patrón Chain of Responsibility (Cadena de responsabilidad)

El patrón **Chain of Responsibility** pasa las solicitudes a lo largo de una cadena de manejadores. Al recibir una solicitud, cada manejador decide procesar la solicitud o pasarla al siguiente manejador de la cadena.

```php
// app/Http/Middleware/RequestHandlerInterface.php
declare(strict_types=1);

namespace App\Http/Middleware;

interface RequestHandlerInterface
{
    public function setNext(RequestHandlerInterface $handler): RequestHandlerInterface;
    public function handle(array $request): ?string;
}

abstract class AbstractRequestHandler implements RequestHandlerInterface
{
    private ?RequestHandlerInterface $nextHandler = null;

    public function setNext(RequestHandlerInterface $handler): RequestHandlerInterface
    {
        $this->nextHandler = $handler;
        return $handler;
    }

    public function handle(array $request): ?string
    {
        if ($this->nextHandler !== null) {
            return $this->nextHandler->handle($request);
        }
        return null;
    }
}

class AuthHandler extends AbstractRequestHandler
{
    public function handle(array $request): ?string
    {
        if (!isset($request['user_id'])) {
            return "401 Unauthorized";
        }
        return parent::handle($request);
    }
}

class RateLimitHandler extends AbstractRequestHandler
{
    public function handle(array $request): ?string
    {
        if (isset($request['request_count']) && $request['request_count'] > 100) {
            return "429 Too Many Requests";
        }
        return parent::handle($request);
    }
}
```

---

<a id="command"></a>
## El Patrón Command (Comando)

El patrón **Command** convierte una solicitud en un objeto independiente que contiene toda la información sobre la solicitud. Esta transformación permite pasar solicitudes como argumentos de métodos, retrasar o encolar la ejecución de una solicitud y admitir operaciones que se pueden deshacer.

```php
// app/Commands/CommandInterface.php
declare(strict_types=1);

namespace App\Commands;

interface CommandInterface
{
    public function execute(): void;
}

class BackupDatabaseCommand implements CommandInterface
{
    private string $databaseName;

    public function __construct(string $databaseName)
    {
        $this->databaseName = $databaseName;
    }

    public function execute(): void
    {
        // Logic to run backup shell scripts...
    }
}

// Invoker class
class CommandQueueExecutor
{
    private array $queue = [];

    public function addCommand(CommandInterface $command): void
    {
        $this->queue[] = $command;
    }

    public function runPendingCommands(): void
    {
        foreach ($this->queue as $command) {
            $command->execute();
        }
        $this->queue = [];
    }
}
```

---

<a id="others"></a>
## Otros Patrones de Comportamiento

- **Iterator**: Permite recorrer los elementos de una colección sin exponer su representación subyacente (lista, pila, árbol, etc.).
- **Mediator**: Restringe las comunicaciones directas entre los objetos y los obliga a colaborar únicamente a través de un objeto mediador.
- **Memento**: Permite guardar y restaurar el estado anterior de un objeto sin revelar los detalles de su implementación.
- **State**: Permite que un objeto altere su comportamiento cuando cambia su estado interno. El objeto parecerá cambiar de clase.
- **Template Method**: Define el esqueleto de un algoritmo en la superclase, pero permite que las subclases sobrescriban pasos específicos del algoritmo sin cambiar su estructura.
- **Visitor**: Permite separar los algoritmos de los objetos sobre los que operan.
- **Interpreter**: Evalúa la gramática o las expresiones de un lenguaje utilizando una estructura de clases especializada.

---

<a id="common-mistakes"></a>
## Errores Comunes

1. **Abusar del patrón Observer**: Crear demasiados suscriptores de eventos globales. Esto provoca efectos secundarios ocultos y dificulta el seguimiento de las acciones durante la depuración.
2. **Acoplamiento fuerte en Chain of Responsibility**: Programar la secuencia directamente dentro de los manejadores en lugar de construir la cadena en una capa de configuración o en un orquestador.
3. **Elegir State en lugar de Strategy**: Añadir propiedades de gestión de estado a objetos Strategy que deberían ser independientes y no tener estado.

---

<a id="checklist"></a>
## Lista de Verificación

1. **Desacoplamiento:** ¿Sus observadores siguen sin conocer la existencia ni los comportamientos de los demás?
2. **Flexibilidad:** ¿Las estrategias pueden cambiarse dinámicamente en tiempo de ejecución sin necesidad de reiniciar el objeto de contexto principal?
3. **Interrupción de la cadena:** ¿Los manejadores de solicitudes interrumpen la ejecución (short-circuit) inmediatamente cuando fallan las condiciones de validación?
4. **Acoplamiento del comando:** ¿La clase invocadora permanece desacoplada del receptor real del comando?

---

<a id="summary"></a>
## Resumen

Los patrones de comportamiento rigen cómo las clases se comunican y comparten responsabilidades. Utilice **Strategy** para intercambiar algoritmos principales sobre la marcha. Utilice **Observer** para diseñar sistemas de notificaciones push o eventos/oyentes. Utilice **Chain of Responsibility** para construir flujos de middleware. Utilice **Command** para representar tareas como objetos en colas de comandos.

---

<a id="self-test-quiz"></a>
## Cuestionario de Autoevaluación

### Pregunta 1: ¿Cuál es el síntoma principal de que debería utilizar el patrón Strategy?
- A) Un único método contiene un bloque masivo de sentencias `if-else` o `switch-case` anidadas que seleccionan diferentes formas de realizar la misma tarea.
- B) Necesita escribir un método de copia de seguridad que guarde los estados de la base de datos.
- C) Desea almacenar en caché dinámicamente los resultados de las consultas a la base de datos.

<details>
<summary><b>Mostrar respuesta</b></summary>

**Respuesta: A**
Cuando ve múltiples condiciones que seleccionan diferentes algoritmos (por ejemplo, `if ($provider === 'Stripe')` o `else if ($provider === 'PayPal')`), esto indica una necesidad clara de Strategy. Reemplazar estos casos por polimorfismo produce un código limpio que cumple con el principio de abierto/cerrado.
</details>

### Pregunta 2: En Chain of Responsibility, ¿cómo deciden los manejadores si deben pasar la solicitud al siguiente?
- A) Cada manejador debe pasar siempre la solicitud; no se permite cortocircuitar la cadena.
- B) Un manejador procesa la solicitud y devuelve un resultado (rompiendo la cadena) o pasa el control al siguiente manejador llamando a su método `handle()`.
- C) La secuencia de la cadena se aleatoriza en cada solicitud.

<details>
<summary><b>Mostrar respuesta</b></summary>

**Respuesta: B**
El diseño central de la cadena de responsabilidad es que los manejadores deciden dinámicamente si cortocircuitan la ejecución (por ejemplo, cuando falla la autenticación) o si delegan el procesamiento a lo largo de la cadena llamando al siguiente manejador.
</details>
