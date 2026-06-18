---
title: "Patrones de arquitectura de microservicios: Saga, CQRS, Event Sourcing y Circuit Breaker | DevSense"
description: "Domina la arquitectura de sistemas distribuidos. Análisis profundo de Database-per-service, patrón Saga, CQRS, Event Sourcing, Circuit Breakers y Service Discovery en PHP."
published: 2026-06-18
faq:
  - question: "¿Cuál es la diferencia entre la coreografía de Saga (Saga Choreography) y la orquestación de Saga (Saga Orchestration)?"
    answer: "La coreografía de Saga depende de que los servicios publiquen y se suscriban a eventos sin un coordinador central, haciendo que los servicios reaccionen a los cambios. La orquestación de Saga utiliza una clase orquestadora central que coordina explícitamente todos los pasos y compensaciones de forma secuencial."
  - question: "¿Cómo mejora CQRS el rendimiento en los microservicios?"
    answer: "CQRS desacopla las lecturas de las escrituras, lo que permite escalar las bases de datos de lectura (como Elasticsearch o réplicas de lectura de Redis) de forma independiente de las bases de datos de escritura (como PostgreSQL). Las lecturas pueden utilizar consultas sencillas y rápidas en vistas desnormalizadas, mientras que las escrituras ejecutan transacciones de validación normalizadas."
  - question: "¿Por qué necesitamos un Circuit Breaker en sistemas distribuidos?"
    answer: "En una red de microservicios, un solo servicio lento o caído puede agotar los hilos o las conexiones en los servicios ascendentes (upstream), provocando un fallo en cascada. Un Circuit Breaker interrumpe inmediatamente las peticiones a un servicio que está fallando (estado Abierto), protegiendo la estabilidad general del sistema."
---

# Patrones de arquitectura de microservicios: Saga, CQRS, Event Sourcing y Circuit Breaker

La transición de un monolito a microservicios resuelve los problemas de escalabilidad y autonomía de los equipos, pero introduce nuevos problemas propios de los sistemas distribuidos. Se pierden las transacciones ACID entre bases de datos, aumenta la latencia de red y los servicios pueden fallar de forma independiente.

Para construir sistemas resilientes y de alto rendimiento, debes aprovechar los patrones de diseño de microservicios. En esta guía, analizaremos cinco patrones fundamentales de microservicios, sus ventajas y desventajas, e implementaremos ejemplos reales en PHP 8.x que muestran cómo funcionan.

**Guías relacionadas:** [Arquitectura de monolito a microservicios](monolith-to-microservices-architecture) · [Comparativa de colas de mensajes](message-queues-compared) · [Rendimiento y escalabilidad de bases de datos](database-performance-and-scaling)

## Contenidos

* [Patrón Database-per-Service](#database-per-service)
* [Patrón Saga (transacciones distribuidas)](#saga-pattern)
* [CQRS y Event Sourcing](#cqrs-event-sourcing)
* [Circuit Breaker (resiliencia)](#circuit-breaker)
* [Service Discovery](#service-discovery)
* [Errores comunes](#common-mistakes)
* [Lista de verificación](#checklist)
* [Resumen](#summary)
* [Prueba de autoevaluación](#self-test-quiz)

---

<a id="database-per-service"></a>
## Patrón Database-per-Service

En un monolito, todos los módulos comparten una única base de datos. En los microservicios, compartir una base de datos rompe los límites de los servicios, introduce un acoplamiento estrecho de esquemas y genera el agotamiento de las conexiones a la base de datos. 

El patrón **Database-per-Service** (base de datos por servicio) dicta que cada servicio es dueño de sus propios datos. Ningún otro servicio puede acceder a esta base de datos directamente. En su lugar, se debe acceder a los datos exclusivamente a través de la API pública del servicio.

| Aspecto | Base de datos compartida (Monolito) | Database-per-Service |
| --- | --- | --- |
| **Acoplamiento** | Alto (cualquier cambio de esquema rompe múltiples módulos) | Bajo (los servicios encapsulan su esquema por completo) |
| **Tecnología** | Un único motor de base de datos (por ejemplo, solo relacional) | Persistencia políglota (por ejemplo, Neo4j para grafos, MongoDB para documentos) |
| **Transacciones** | Transacciones ACID (reversión fácil, claves foráneas) | Transacciones distribuidas (requiere Sagas, consistencia eventual) |

---

<a id="saga-pattern"></a>
## Patrón Saga (transacciones distribuidas)

Dado que las bases de datos están aisladas, no se puede ejecutar un `START TRANSACTION` estándar a través de múltiples servicios. El **Patrón Saga** coordina una secuencia de transacciones locales. Cada transacción actualiza el estado de la base de datos dentro de un solo servicio y publica un evento.

Si una transacción falla (por ejemplo, pago insuficiente), la Saga ejecuta **transacciones compensatorias** en orden inverso para revertir los cambios realizados por los pasos anteriores.

Existen dos estrategias de coordinación:
1. **Coreografía (Choreography):** Descentralizada. Los servicios escuchan eventos y desencadenan sus acciones locales.
2. **Orquestación (Orchestration):** Centralizada. Un servicio controlador u orquestador indica a los participantes qué acciones ejecutar.

### Implementación de un orquestador de Saga en PHP

Aquí se muestra un orquestador de Saga centralizado que coordina una Saga de creación de pedidos.

```php
// app/Sagas/OrderSagaOrchestrator.php
declare(strict_types=1);

namespace App\Sagas;

use App\Sagas\Steps\SagaStepInterface;
use Exception;
use Psr\Log\LoggerInterface;

class OrderSagaOrchestrator
{
    private array $steps = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function addStep(SagaStepInterface $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    public function execute(array $payload): bool
    {
        $executedSteps = [];

        foreach ($this->steps as $step) {
            try {
                $this->logger->info("Executing step: " . get_class($step));
                $step->execute($payload);
                $executedSteps[] = $step;
            } catch (Exception $e) {
                $this->logger->error("Saga step failed: " . $e->getMessage() . ". Starting compensations.");
                $this->compensate($executedSteps, $payload);
                return false;
            }
        }

        return true;
    }

    private function compensate(array $executedSteps, array $payload): void
    {
        // Compensate executed steps in reverse order (LIFO)
        foreach (array_reverse($executedSteps) as $step) {
            try {
                $this->logger->warning("Compensating step: " . get_class($step));
                $step->compensate($payload);
            } catch (Exception $e) {
                $this->logger->critical("Compensation failed for " . get_class($step) . ": " . $e->getMessage());
                // In production, queue failed compensations for manual recovery/retries
            }
        }
    }
}
```

```php
// app/Sagas/Steps/SagaStepInterface.php
declare(strict_types=1);

namespace App\Sagas\Steps;

interface SagaStepInterface
{
    public function execute(array $payload): void;
    public function compensate(array $payload): void;
}
```

---

<a id="cqrs-event-sourcing"></a>
## CQRS y Event Sourcing

En una arquitectura de base de datos desacoplada, consultar datos entre servicios requiere costosas uniones (joins) HTTP. 

**CQRS (Segregación de Responsabilidad de Comando y Consulta)** separa las operaciones que modifican datos (Comandos) de las operaciones que leen datos (Consultas). 
**Event Sourcing** (abastecimiento de eventos) va más allá: en lugar de almacenar el estado actual de una entidad, almacena cada transición de estado como un registro cronológico de eventos (event log).

El lado de escritura registra los eventos (por ejemplo, `OrderCreated`, `PaymentCompleted`). Un proyector en segundo plano escucha estos eventos y construye un modelo de lectura desnormalizado (por ejemplo, en Elasticsearch o en una tabla de base de datos plana) optimizado puramente para consultas de lectura rápidas.

```php
// app/CQRS/OrderCommandHandler.php
declare(strict_types=1);

namespace App\CQRS;

use App\Events\OrderCreatedEvent;
use App\Models\EventStore;

class OrderCommandHandler
{
    private EventStore $eventStore;
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventStore $eventStore, EventDispatcherInterface $dispatcher)
    {
        $this->eventStore = $eventStore;
        $this->dispatcher = $dispatcher;
    }

    public function handle(CreateOrderCommand $command): void
    {
        // 1. Validate domain logic
        if ($command->amount <= 0) {
            throw new \InvalidArgumentException("Order amount must be positive.");
        }

        // 2. Generate Domain Event
        $event = new OrderCreatedEvent(
            orderId: uniqid('ord_', true),
            userId: $command->userId,
            amount: $command->amount,
            createdAt: new \DateTimeImmutable()
        );

        // 3. Persist Event to Append-Only Event Store
        $this->eventStore->append(
            aggregateId: $event->orderId,
            aggregateType: 'Order',
            eventName: 'OrderCreated',
            payload: $event->toArray()
        );

        // 4. Dispatch event to update projections (Read Database) asynchronously
        $this->dispatcher->dispatch($event);
    }
}
```

---

<a id="circuit-breaker"></a>
## Circuit Breaker (resiliencia)

Las redes distribuidas fallan. Si el Servicio A llama al Servicio B, y el Servicio B experimenta latencia o está caído, los hilos (threads) del Servicio A se bloquearán, propagando el fallo hacia arriba en cascada.

Un **Circuit Breaker** (cortocircuito) actúa como una envoltura de fusible eléctrico alrededor de las llamadas remotas. Realiza un seguimiento de los fallos y realiza transiciones a través de tres estados:
* **Cerrado (Closed):** Funcionamiento normal. Las solicitudes fluyen hacia el servicio remoto.
* **Abierto (Open):** El servicio remoto está fallando. Las solicitudes se bloquean inmediatamente, devolviendo un valor de contingencia (fallback) local.
* **Semiabierto (Half-Open):** El periodo de enfriamiento ha expirado. Se envía un número limitado de solicitudes para comprobar si el servicio remoto vuelve a estar sano.

```php
// app/CircuitBreaker/CircuitBreaker.php
declare(strict_types=1);

namespace App\CircuitBreaker;

use App\Services\CacheInterface;
use RuntimeException;

class CircuitBreaker
{
    private const STATE_CLOSED = 'CLOSED';
    private const STATE_OPEN = 'OPEN';
    private const STATE_HALF_OPEN = 'HALF_OPEN';

    private CacheInterface $cache;
    private string $serviceName;
    private int $failureThreshold;
    private int $cooldownPeriod; // In seconds

    public function __construct(
        CacheInterface $cache,
        string $serviceName,
        int $failureThreshold = 5,
        int $cooldownPeriod = 30
    ) {
        $this->cache = $cache;
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->cooldownPeriod = $cooldownPeriod;
    }

    public function call(callable $remoteCall, callable $fallback): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if ($this->hasCooldownExpired()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
            } else {
                return $fallback(); // Immediate fallback, no remote call
            }
        }

        try {
            $result = $remoteCall();
            $this->resetFailures();
            return $result;
        } catch (\Exception $e) {
            $this->handleFailure();
            return $fallback();
        }
    }

    private function getState(): string
    {
        return $this->cache->get("cb:{$this->serviceName}:state") ?: self::STATE_CLOSED;
    }

    private function transitionTo(string $state): void
    {
        $this->cache->set("cb:{$this->serviceName}:state", $state);
        if ($state === self::STATE_OPEN) {
            $this->cache->set("cb:{$this->serviceName}:open_time", time());
        }
    }

    private function handleFailure(): void
    {
        $failures = (int)$this->cache->get("cb:{$this->serviceName}:failures") + 1;
        $this->cache->set("cb:{$this->serviceName}:failures", $failures);

        if ($failures >= $this->failureThreshold) {
            $this->transitionTo(self::STATE_OPEN);
        }
    }

    private function resetFailures(): void
    {
        $this->cache->set("cb:{$this->serviceName}:failures", 0);
        $this->transitionTo(self::STATE_CLOSED);
    }

    private function hasCooldownExpired(): bool
    {
        $openTime = (int)$this->cache->get("cb:{$this->serviceName}:open_time");
        return (time() - $openTime) >= $this->cooldownPeriod;
    }
}
```

---

<a id="service-discovery"></a>
## Service Discovery

En entornos de microservicios, las instancias se escalan horizontalmente hacia arriba y hacia abajo de forma dinámica, lo que significa que las direcciones IP cambian constantemente. Es imposible codificar URLs fijas (hardcoding) dentro de los archivos de configuración.

**Service Discovery** (descubrimiento de servicios) proporciona un registro de servicios (por ejemplo, Consul, Eureka) que actúa como un directorio telefónico distribuido.
* **Registro de servicios (Service Registration):** Cuando una instancia se inicia, llama al registro para grabar su nombre e IP/puerto actual. Envía comprobaciones de estado (health checks) periódicas para permanecer en el registro.
* **Búsqueda de servicios (Service Lookup):** Cuando el Servicio A quiere llamar al Servicio B, consulta al registro para obtener una lista de direcciones IP activas y luego aplica balanceo de carga.

---

<a id="common-mistakes"></a>
## Errores comunes

1. **Bases de datos compartidas:** Ejecutar múltiples microservicios contra el mismo esquema de base de datos, lo que resulta en un acoplamiento de dependencias oculto y bloqueos en la base de datos.
2. **Ausencia de pasos de compensación en las Sagas:** Construir Sagas sin escribir acciones compensatorias robustas, dejando los datos en un estado corrupto cuando las transacciones fallan a mitad del camino.
3. **Event Sourcing sin instantáneas (snapshots):** Volver a leer miles de eventos históricos para construir el estado actual en cada solicitud, lo que genera una enorme sobrecarga de CPU y base de datos. Crea instantáneas de estado periódicamente (por ejemplo, cada 100 eventos).
4. **Ignorar los enfriamientos del Circuit Breaker:** Mantener el Circuit Breaker abierto para siempre o volver al estado cerrado sin realizar pruebas con solicitudes limitadas primero (estado Semiabierto).

---

<a id="checklist"></a>
## Lista de verificación

1. **Aislamiento de datos:** ¿Tu servicio accede directamente a las tablas de la base de datos de otro servicio? Si es así, refactoriza para usar llamadas a APIs HTTP/gRPC.
2. **Seguridad en transacciones distribuidas:** Si un paso de pago en la creación de un pedido falla, ¿tienes un disparador de compensación que cancele el pedido en tu base de datos?
3. **Aislamiento de la proyección CQRS:** ¿Tu base de datos de proyección de lectura de CQRS se ejecuta en una réplica de lectura separada o en un almacén de datos especializado, o sigues ejecutando consultas JOIN lentas en la base de datos de escritura?
4. **Envoltura de resiliencia:** ¿Has envuelto tus llamadas a APIs externas en un patrón de Circuit Breaker para evitar fallos de red en cascada?

---

<a id="summary"></a>
## Resumen

Los patrones arquitectónicos de microservicios construyen una alta escalabilidad y tolerancia a fallos. Sé dueño de tus datos con **Database-per-Service**. Evita el bloqueo global utilizando **Patrones Saga** para gestionar transacciones de consistencia eventual. Escala el tráfico de lectura con **CQRS** y registra el historial con **Event Sourcing**. Protege tus servicios de caídas en cascada utilizando **Circuit Breakers**. Mapea instancias dinámicamente utilizando registros de **Service Discovery**.

---

<a id="self-test-quiz"></a>
## Prueba de autoevaluación

### Pregunta 1: ¿Cuál es el propósito principal de las transacciones compensatorias en el patrón Saga?
- A) Optimizar los planes de ejecución SQL en servidores PostgreSQL.
- B) Revertir los efectos de las transacciones locales completadas anteriormente en la secuencia cuando falla un paso posterior en la Saga.
- C) Encriptar las cargas útiles (payloads) de los eventos antes de enviarlas a través de flujos de Redis (Redis streams).

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: B**
Dado que los microservicios no comparten transacciones de base de datos, las Sagas logran la consistencia ejecutando acciones compensatorias en orden inverso para anular las actualizaciones anteriores si el proceso de negocio falla a mitad de la ejecución.
</details>

### Pregunta 2: En CQRS / Event Sourcing, ¿cuál es el rol de una "Proyección" (Projection)?
- A) Renderizar gráficos y diagramas de panel de control en el panel de administración.
- B) Calcular la capacidad de almacenamiento futuro requerida por las bases de datos.
- C) Compilar el registro de eventos de solo adición (append-only) cronológicamente en un modelo desnormalizado y optimizado para lecturas para realizar consultas rápidas.

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: C**
Las proyecciones escuchan el flujo de eventos agregados (como OrderCreated, OrderShipped) y actualizan una base de datos de lectura plana y desnormalizada (como una base de datos de documentos o una base de datos réplica) optimizada específicamente para consultas de selección rápidas y amigables con los índices.
</details>

### Pregunta 3: ¿Qué sucede cuando un Circuit Breaker realiza una transición al estado 'Abierto' (Open)?
- A) Se permite el paso directo de todas las solicitudes para verificar el estado de salud del servicio.
- B) Las solicitudes se bloquean de inmediato y se redirigen a alternativas (fallbacks) locales sin llamar al servicio remoto, ahorrando recursos.
- C) La aplicación genera automáticamente nuevas instancias de Docker a través de Docker Compose.

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: B**
Cuando un servicio falla repetidamente, el Circuit Breaker se abre para evitar bloquear los hilos de conexión. Cualquier llamada posterior falla de inmediato, omitiendo por completo la llamada de red remota y ejecutando una rutina de contingencia (fallback).
</details>
