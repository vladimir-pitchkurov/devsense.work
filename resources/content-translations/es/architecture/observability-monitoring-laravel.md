---
title: "Observabilidad: logs, métricas y salud para Laravel y microservicios | DevSense"
description: "Cómo monitorear el estado de la aplicación en todos los entornos y cargas: registro estructurado, métricas, trazas, IDs de correlación entre servicios y un espectro práctico de herramientas que van desde el clásico syslog hasta Prometheus, Loki, OpenTelemetry y APM SaaS."
published: 2026-04-15
faq:
  - question: "¿Cuál es la diferencia entre el nivel de log (log level) y el contexto de log (log context)?"
    answer: "El nivel de log (por ejemplo, DEBUG, INFO, WARNING, ERROR) categoriza la gravedad de un mensaje, lo que permite realizar filtros en producción. El contexto del log son metadatos estructurados (arrays que contienen IDs de usuario, IDs de solicitud, tiempos de ejecución) adjuntos a la entrada del log, lo que permite la indexación y correlación automatizadas sin analizar texto no estructurado."
  - question: "¿Por qué las etiquetas (labels) de alta cardinalidad son malas para los sistemas de métricas como Prometheus?"
    answer: "Prometheus almacena métricas como entradas de bases de datos de series temporales. Cada combinación única de etiquetas crea una nueva serie temporal. Almacenar parámetros de alta cardinalidad (como IDs de usuario o URLs dinámicas completas) como etiquetas da como resultado una 'explosión de series', lo que agota la memoria de Prometheus y colapsa el servidor de métricas."
  - question: "¿Cómo propaga el rastreo distribuido la correlación a través de los microservicios?"
    answer: "El rastreo distribuido utiliza encabezados HTTP (como `X-Request-Id` o `traceparent` de W3C) para pasar un ID de traza único y un ID de span a lo largo de la cadena de solicitudes. Cada microservicio en el flujo de solicitudes extrae este encabezado, lo incluye en sus propios logs y lo inyecta en las solicitudes de clientes HTTP salientes o en los encabezados de mensajes de cola."
  - question: "¿Por qué debería desactivarse Laravel Telescope en producción?"
    answer: "Laravel Telescope captura el contexto detallado de las solicitudes, las consultas de la base de datos y los registros de logs, almacenándolos en la base de datos. En un entorno de producción de alto tráfico, esta sobrecarga de consultas y almacenamiento degrada el rendimiento de la aplicación, aumenta la carga de la base de datos y puede consumir un espacio de almacenamiento excesivo."
---

# Observabilidad: logs, métricas y salud en monolitos y microservicios de Laravel

“Funciona en mi máquina” no es una estrategia de monitoreo. La producción se rompe de **formas específicas**: el disco se llena, la latencia de la cola aumenta, una dependencia agota su tiempo de espera o una réplica sirve lecturas obsoletas. Una buena observabilidad te permite responder **qué cambió**, **para quién** y **qué salto (hop) falló**, sin conectarte por SSH a cada servidor. Las mismas ideas se aplican tanto si ejecutas un **monolito de Laravel** como una **flota de servicios**; solo que las **conexiones** y la **cardinalidad** se vuelven más difíciles a medida que divides los límites.

**Guías relacionadas:** [PHP database connection pooling](php-database-connection-pooling) · [Databases under load](database-performance-and-scaling) · [API gateway & messaging](../microservices/api-gateway)

## Índice

* [Tres pilares: logs, métricas, trazas](#pillars)
* [Qué varía según el entorno](#environments)
* [Monolito de Laravel: capas prácticas](#monolith)
* [Microservicios: correlación y rastreo](#microservices)
* [Espectro de herramientas: de lo clásico a lo moderno](#tools)
* [Bajo carga: muestreo, cardinalidad, costo](#load)
* [Alertas que los humanos respetarán](#alerting)
* [Errores comunes](#common-mistakes)
* [Lista de verificación](#checklist)
* [Cuestionario de autoevaluación](#self-test-quiz)

---

<a id="pillars"></a>
## Tres pilares: logs, métricas, trazas

| Pilar | Respuestas | Errores comunes |
|--------|---------|------------------|
| **Logs** | *¿Qué narrativa ocurrió?* (errores, auditoría, contexto de depuración) | Líneas individuales no estructuradas que no se pueden consultar; registrar secretos; saturación de logs **INFO** en producción |
| **Métricas** | *¿Cuánto, qué tan rápido, en comparación con ayer?* (tasas, histogramas, saturación) | Etiquetas (labels) de **alta cardinalidad** (URL completa, ID de usuario en cada serie); gráficos que nadie mira |
| **Trazas (Traces)** | *¿Qué intervalo (span) en la cadena fue lento?* | Falta de **propagación del contexto**, por lo que el microservicio B no tiene idea de que pertenece a la solicitud A |

Se refuerzan mutuamente: un **pico en la tasa de errores** (métrica) te lleva a **logs representativos** y a una **traza** de un pago lento. Ningún pilar sustituye a los demás.

---

<a id="environments"></a>
## Qué varía según el entorno

* **Local / dev** — maximiza la **velocidad del desarrollador**: `tail`, UIs de depuración tipo Telescope, logs detallados, puntos de interrupción (breakpoints). **No** envíes esa verbosidad a producción sin cambios.
* **Staging / pre-prod** — refleja destinos de logs y paneles **similares a producción** donde sea asequible; captura errores de la clase “funciona hasta que habilitamos JSON hacia Loki”.
* **Producción** — optimiza para el **señal**, el **costo de retención** y los **valores predeterminados seguros**: logs estructurados, redacción de datos sensibles, muestreo (sampling) en rutas de depuración, **pruebas de salud** que reflejen dependencias reales.

> [!NOTE]
> **Principio de compatibilidad**
> El objetivo no es tener herramientas idénticas en todas partes, sino **contratos compatibles** (mismos nombres de campos de correlación, mismos nombres de métricas) para que los incidentes no requieran volver a aprender el funcionamiento del sistema.

---

<a id="monolith"></a>
## Monolito de Laravel: capas prácticas

### Registro de la aplicación (Logging)

* Usa los canales de **`config/logging.php`**: `stack`, `daily`, `syslog` o un formateador **JSON** hacia la salida estándar (stdout) para que los hosts de contenedores lo recopilen.
* Prefiere **campos estructurados** (arrays de `context`) en lugar de analizar oraciones descriptivas más tarde.
* Adjunta el **ID de solicitud (request id)**, **ID de usuario** (si la política lo permite), **ID de trabajo de la cola**; cualquier cosa que te ayude a pivotar desde una línea de log al resto de la historia.

Ejemplo de contexto estructurado:
```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily'],
        'ignore_exceptions' => false,
    ],
    // ...
],
```

Y utilización en código:
```php
// app/Http/Controllers/OrderController.php
Log::info('Order processed successfully', [
    'order_id' => $order->id,
    'user_id' => auth()->id(),
    'execution_time_ms' => $timeMs,
]);
```

### Ciclo de vida de la solicitud

* Middleware para el **ID de correlación**: acepta un `X-Request-Id` entrante o genera uno; devuélvelo en las respuestas; pásalo a los trabajos y a los clientes HTTP.
* La función **`Log::withContext()`** de Laravel ayuda a mantener el contexto en la solicitud sin pasar parámetros por todas partes.

### Colas y programaciones (Queues & Schedules)

* **Horizon** (Redis) proporciona **profundidad de la cola, rendimiento, trabajos fallidos**; trátalo como **monitoreo de primera clase**, no como una interfaz opcional.
* Tareas programadas: registra **inicio/fin/duración**; alerta sobre **ejecuciones omitidas** (monitoreo de cron o latidos/heartbeats externos).

### Introspección profunda (no producción)

* **Telescope** es invaluable para **local/staging**; mantenlo **desactivado** en producción a menos que tengas filtros estrictos (IP, autenticación, muestreo) y asumas la sobrecarga.
* **Laravel Pulse** muestra **consultas lentas, excepciones, colas** en un panel de control; aun así, ten en cuenta el **muestreo y la retención** en aplicaciones con mucho tráfico.

### Salud y preparación (Health & Readiness)

* **`/up` (Salud)** en Laravel 11+: distingue la **supervivencia (liveness)** (“el proceso se ejecuta”) de la **preparación (readiness)** (“puede comunicarse con la BD y el caché”). Los balanceadores de carga y las sondas de Kubernetes se preocupan por esta diferencia.

### Errores como producto

* **Sentry**, **Flare**, **Bugsnag**: trazas de pila agrupadas, seguimiento de versiones, migas de pan (breadcrumbs). Complementan los logs; no reemplazan las **métricas de saturación**.

---

<a id="microservices"></a>
## Microservicios: correlación y rastreo

Cuando una llamada HTTP se convierte en **gateway → servicio A → servicio B → broker → worker**, un simple log de acceso por servicio es **insuficiente**.

### ID de correlación

* Propaga un ID estable en cada llamada saliente (`X-Request-Id` o **`traceparent` de W3C** junto con tu ID interno).
* Regístralo en **cada** servicio al ingresar; inclúyelo en cargas útiles **asíncronas** (carga útil del trabajo, encabezados de mensajes).

### Rastreo distribuido

* **OpenTelemetry** es la forma emergente e **independiente del proveedor** para emitir trazas; los recolectores (collectors) las envían a **Jaeger**, **Tempo**, **Zipkin** o backends SaaS.
* Los ecosistemas PHP varían en madurez: verifica la **instrumentación** para tu cliente HTTP, controlador de BD y biblioteca de colas. Un rastreo parcial sigue siendo mejor que nada.

### Límites de servicio

* Estandariza las políticas de **tiempo de espera (timeout), reintento e idempotencia**; la observabilidad mostrará **reintentos en cascada** si cada capa reintenta ciegamente.

---

<a id="tools"></a>
## Espectro de herramientas: de lo clásico a lo moderno

### Era de host y red

* **syslog**, **rsyslog**, **logrotate** — centralizan archivos planos; siguen siendo válidos como etapa de **transporte**.
* **Nagios**, **Icinga**, **Zabbix** — comprobaciones de host, ping, disco, sondas de servicio simples. Menos orientados a las **trazas de aplicaciones**, pero comunes para las **métricas base de infraestructura**.

### Agregación de logs

* **ELK / Elastic Stack** (Elasticsearch, Logstash/Beats, Kibana) — búsqueda potente; administra la infraestructura o adquiere capacidad de forma consciente.
* **Graylog**, **Splunk** (empresarial) — espacio de soluciones similar.

### Métricas y paneles de control

* Modelo de extracción (scrape) de **Prometheus** + paneles de **Grafana** — estándar de facto para **Kubernetes** y muchos entornos locales; **Alertmanager** para enrutamiento.
* **VictoriaMetrics**, **Mimir**, **Thanos** — variantes a largo plazo o de alta disponibilidad sobre los protocolos de Prometheus.

### Logs “como métricas”

* **Grafana Loki** — almacenamiento de logs basado en etiquetas que se combina de forma natural con Grafana; a menudo más económico que indexar cada campo como los motores de búsqueda.

### Nativo de la nube

* **AWS CloudWatch**, **Google Cloud Logging/Monitoring**, **Azure Monitor** — integración estrecha si ya utilizas esas plataformas.

### SaaS todo en uno

* **Datadog**, **New Relic**, **Honeycomb** — logs, métricas, APM, RUM; **rápida obtención de valor**, **precios por volumen**; vigila la cardinalidad.

### Errores y APM para PHP

* **Sentry** (errores + rendimiento), **Scout**, **Tideways** (perfilado enfocado en PHP) — fuerte uso en la comunidad de Laravel.

### Ola de estandarización

* **OpenTelemetry (OTel)** — SDKs/exportadores unificados; el **recolector** puede distribuir los datos a muchos backends. **La adopción está creciendo** precisamente para evitar la dependencia de proveedores por cada señal.

---

<a id="load"></a>
## Bajo carga: muestreo, cardinalidad, costo

* El **volumen de logs** crece linealmente con el tráfico; usar **JSON por solicitud** en nivel `debug` puede sobrecargar la CPU de la aplicación. Usa **niveles** y **muestreo de depuración** para rutas calientes.
* **Etiquetas de Prometheus**: nunca uses valores **ilimitados** (URLs con IDs dinámicos, correos electrónicos) como nombres de etiquetas o valores de alta cardinalidad; de lo contrario, **las métricas explotarán**.
* **Muestreo de trazas**: conserva el **100%** de los errores o solicitudes lentas; muestrea el resto; los backends y tu presupuesto te lo agradecerán.
* **Retención**: define datos **calientes** (días) frente a **fríos** (almacenamiento de objetos) frente a **eliminación**; el cumplimiento normativo puede exigir una retención de **auditoría** más larga, separada de los logs de **depuración**.

---

<a id="alerting"></a>
## Alertas que los humanos respetarán

Alerta sobre fallas **visibles para el usuario** o **inminentes**: consumo del presupuesto de errores (**SLO burn**), aumento en la **tasa de errores**, tiempo de espera de colas **p95**, límite de **disco**, vencimiento de **certificados**.

Evita alertar sobre condiciones **ruidosas conocidas** a menos que adjuntes **manuales de procedimientos (runbooks)**. Un “CPU > 80%” durante cinco minutos a menudo **no** es un incidente; una caída de 10 veces en la **tasa de éxito de pagos** sí lo es.

---

<a id="common-mistakes"></a>
## Errores comunes

1. **Etiquetas de métricas de alta cardinalidad**: Agregar identificadores únicos como `user_id` o parámetros de `url` generados dinámicamente como etiquetas de Prometheus, lo que agota la memoria.
2. **Filtrar credenciales en los logs**: Registrar `$request->all()` en endpoints de inicio de sesión o restablecimiento de contraseña, exponiendo contraseñas, tokens e información de identificación personal (PII).
3. **Dejar Telescope habilitado en producción**: Almacenamiento ilimitado en la base de datos de los intervalos de la aplicación, lo que ralentiza las consultas y sobrecarga el almacenamiento primario.
4. **Falta de configuración de tiempos de espera en solicitudes externas**: Llamar a APIs de terceros sin un tiempo de espera, lo que provoca que los procesos HTTP se bloqueen y agoten los procesos secundarios de FPM.

---

<a id="checklist"></a>
## Lista de verificación

1. **Logs estructurados** hacia stdout o un transportador; **un** ID de correlación en tareas síncronas y asíncronas.
2. **Señales doradas (golden signals)** por servicio: latencia, tráfico, errores, saturación (más **profundidad de cola** para los trabajadores de Laravel).
3. Endpoints de **salud** que prueben dependencias **reales**; separa la **supervivencia (liveness)** de la **preparación (readiness)** donde los orquestadores lo requieran.
4. **Rastreador de errores** en producción; herramientas **tipo Telescope** restringidas a entornos que no sean de producción.
5. Revisión de **costos y cardinalidad** antes de habilitar “registrar todo” o “etiquetar todo”.

---

## Resumen

La observabilidad es **parte del producto**: el mismo código de Laravel que sirve a los usuarios debe informarte, **con evidencia**, cuándo está a punto de fallar y **dónde** mirar primero.

---

<a id="self-test-quiz"></a>
## Cuestionario de autoevaluación

### Pregunta 1: ¿Cuál es el principal problema de agregar correos electrónicos de usuarios como etiquetas en una base de datos de métricas de Prometheus?
- A) Prometheus no admite valores de cadena.
- B) Provoca una explosión de cardinalidad de métricas, lo que colapsa la base de datos de métricas.
- C) Viola las políticas estándar de formato de URL.

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: B**
Cada etiqueta de correo electrónico única crea una nueva serie temporal. Si tienes miles de usuarios, Prometheus se quedará rápidamente sin memoria debido a la explosión de cardinalidad.
</details>

---

### Pregunta 2: En un entorno de Kubernetes o balanceado de carga, ¿cuál es el propósito de la prueba de preparación (readiness check)?
- A) Verificar si el proceso del servidor se ha iniciado.
- B) Verificar si la aplicación está lista para aceptar tráfico (por ejemplo, la conexión a la base de datos está activa).
- C) Rastrear consultas lentas.

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: B**
Una sonda de preparación verifica si el contenedor está listo para manejar el tráfico web entrante. Si falla, el balanceador deja de enviar solicitudes a ese nodo.
</details>
