---
title: "Observabilité : journaux, métriques et santé pour Laravel et les microservices | DevSense"
description: "Comment surveiller l'état de l'application à travers les environnements et la charge : journalisation structurée, métriques, traces, identifiants de corrélation inter-services et un éventail pratique d'outils allant du syslog classique à Prometheus, Loki, OpenTelemetry et SaaS APM."
published: 2026-04-15
faq:
  - question: "Quelle est la différence entre le niveau de journalisation (log level) et le contexte de journalisation (log context) ?"
    answer: "Le niveau de journalisation (par exemple, DEBUG, INFO, WARNING, ERROR) catégorise la gravité d'un message, permettant de le filtrer en production. Le contexte de journalisation est un ensemble de métadonnées structurées (tableaux contenant des identifiants d'utilisateurs, des identifiants de requêtes, des temps d'exécution) attachées à l'entrée du journal, permettant l'indexation et la corrélation automatisées sans analyse de texte non structuré."
  - question: "Pourquoi les étiquettes (labels) à forte cardinalité sont-elles mauvaises pour les systèmes de métriques comme Prometheus ?"
    answer: "Prometheus stocke les métriques sous forme d'entrées de base de données de séries chronologiques. Chaque combinaison unique d'étiquettes crée une nouvelle série chronologique. Stocker des paramètres à forte cardinalité (comme des identifiants d'utilisateurs ou des URL dynamiques complètes) en tant qu'étiquettes entraîne une 'explosion de séries', ce qui épuise la mémoire de Prometheus et fait planter le serveur de métriques."
  - question: "Comment le traçage distribué propage-t-il la corrélation à travers les microservices ?"
    answer: "Le traçage distribué utilise des en-têtes HTTP (tels que `X-Request-Id` ou `traceparent` de W3C) pour transmettre un identifiant de trace unique et un identifiant de span tout au long de la chaîne de requêtes. Chaque microservice dans le flux de la requête extrait cet en-tête, l'inclut dans ses propres journaux et l'injecte dans les requêtes de client HTTP sortantes ou les en-têtes de messages de file d'attente."
  - question: "Pourquoi Laravel Telescope devrait-il être désactivé en production ?"
    answer: "Laravel Telescope capture le contexte détaillé des requêtes, les requêtes de base de données et les enregistrements de journaux, en les stockant dans la base de données. Dans un environnement de production à fort trafic, cette surcharge de requêtes et de stockage dégrade le débit de l'application, augmente la charge de la base de données et peut consommer un espace de stockage excessif."
---

# Observabilité : journaux, métriques et santé dans les monolithes Laravel et les microservices

« Fonctionne sur ma machine » n'est pas une stratégie de surveillance. La production tombe en panne de **manière spécifique** : le disque se remplit, la latence de la file d'attente augmente, une dépendance expire ou un réplica sert des données obsolètes. Une bonne observabilité vous permet de répondre à **qu'est-ce qui a changé**, **pour qui** et **quel saut (hop) a échoué** — sans avoir à vous connecter en SSH sur chaque serveur. Les mêmes concepts s'appliquent que vous exécutiez un **monolithe Laravel** ou une **flotte de services** ; seuls l'**assemblage** et la **cardinalité** deviennent plus complexes à mesure que vous divisez les frontières applicatives.

**Guides associés :** [PHP database connection pooling](php-database-connection-pooling) · [Databases under load](database-performance-and-scaling) · [API gateway & messaging](../microservices/api-gateway)

## Table des matières

* [Les trois piliers : journaux, métriques, traces](#pillars)
* [Ce qui diffère selon l'environnement](#environments)
* [Monolithe Laravel : couches pratiques](#monolith)
* [Microservices : corrélation et traçage](#microservices)
* [Éventail d'outils : du classique au moderne](#tools)
* [Sous charge : échantillonnage, cardinalité, coût](#load)
* [Des alertes que les humains respecteront](#alerting)
* [Erreurs courantes](#common-mistakes)
* [Liste de contrôle](#checklist)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="pillars"></a>
## Les trois piliers : journaux, métriques, traces

| Pilier | Réponses | Erreurs courantes |
|--------|---------|------------------|
| **Journaux (Logs)** | *Quel récit s'est produit ?* (erreurs, audit, contexte de débogage) | Lignes uniques non structurées impossibles à requêter ; journalisation de secrets ; inondation d'**INFO** en production |
| **Métriques** | *Combien, à quelle vitesse, par rapport à hier ?* (débits, histogrammes, saturation) | Étiquettes (labels) à **forte cardinalité** (URL complète, identifiant utilisateur sur chaque série) ; graphiques que personne ne regarde |
| **Traces** | *Quel span de la chaîne était lent ?* | Absence de **propagation du contexte**, de sorte que le microservice B n'a aucune idée qu'il appartient à la requête A |

Ils se renforcent mutuellement : un **pic de taux d'erreur** (métrique) vous renvoie à des **journaux représentatifs** et à une **trace** d'un processus de paiement lent. Aucun pilier ne remplace les autres.

---

<a id="environments"></a>
## Ce qui diffère selon l'environnement

* **Local / dev** — maximiser la **vitesse du développeur** : `tail`, interfaces de débogage de type Telescope, journaux détaillés, points d'arrêt (breakpoints). **N'envoyez pas** cette verbosité en production de manière inchangée.
* **Staging / pré-production** — miroir des réceptacles de journaux et des tableaux de bord **semblables à la prod** dans la mesure du possible ; capture des erreurs de type « fonctionne jusqu'à ce que nous activions le format JSON vers Loki ».
* **Production** — optimiser pour le **signal**, le **coût de rétention** et les **valeurs par défaut sécurisées** : journaux structurés, masquage des données sensibles (redaction), échantillonnage sur les chemins de débogage, **tests de santé (health checks)** qui reflètent les dépendances réelles.

> [!NOTE]
> **Principe de compatibilité**
> L'objectif n'est pas d'avoir des outils identiques partout — c'est d'avoir des **contrats compatibles** (mêmes noms de champs de corrélation, mêmes noms de métriques) afin que les incidents ne nécessitent pas de réapprendre le fonctionnement du système.

---

<a id="monolith"></a>
## Monolithe Laravel : couches pratiques

### Journalisation applicative

* Utilisez les canaux de **`config/logging.php`** : `stack`, `daily`, `syslog`, ou un formateur **JSON** vers la sortie standard (stdout) pour que les conteneurs les collectent.
* Préférez les **champs structurés** (tableaux `context`) plutôt que d'analyser des phrases rédigées plus tard.
* Attachez l'**identifiant de requête**, l'**identifiant d'utilisateur** (si la politique le permet), l'**identifiant de tâche de file d'attente** — tout ce qui vous aide à relier une ligne de journal au reste de l'histoire.

Exemple de contexte structuré :
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

Et utilisation dans le code :
```php
// app/Http/Controllers/OrderController.php
Log::info('Order processed successfully', [
    'order_id' => $order->id,
    'user_id' => auth()->id(),
    'execution_time_ms' => $timeMs,
]);
```

### Cycle de vie des requêtes

* Middleware pour l'**identifiant de corrélation** : acceptez un en-tête `X-Request-Id` entrant ou générez-en un ; renvoyez-le dans les réponses ; transmettez-le aux tâches d'arrière-plan et aux clients HTTP.
* La méthode **`Log::withContext()`** de Laravel permet de conserver le contexte sur la requête sans avoir à passer des paramètres partout.

### Files d'attente et planifications (Queues & Schedules)

* **Horizon** (Redis) fournit des informations sur **la profondeur de la file, le débit, les tâches ayant échoué** — traitez-le comme un **outil de surveillance de premier ordre**, et non comme une interface facultative.
* Tâches planifiées : consignez le **début, la fin et la durée** ; alertez en cas de **tâches manquées** (surveillance cron ou signal de présence (heartbeat) externe).

### Introspection profonde (hors production)

* **Telescope** est inestimable en **local/staging** ; laissez-le **désactivé** en production à moins d'avoir des filtres stricts (IP, authentification, échantillonnage) et d'en accepter la surcharge.
* **Laravel Pulse** affiche les **requêtes lentes, les exceptions, les files d'attente** dans un tableau de bord — surveillez tout de même l'**échantillonnage et la rétention** sur les applications à fort trafic.

### Santé et disponibilité (Health & Readiness)

* **`/up` (Health)** dans Laravel 11+ : distinguez la **survie (liveness)** (« le processus s'exécute ») de la **disponibilité (readiness)** (« peut communiquer avec la BD et le cache »). Les répartiteurs de charge et les sondes Kubernetes ont besoin de cette distinction.

### Suivi des erreurs

* **Sentry**, **Flare**, **Bugsnag** — traces de pile regroupées, suivi des versions, fils d'Ariane (breadcrumbs). Ils complètent les journaux ; ils ne remplacent pas les **métriques de saturation**.

---

<a id="microservices"></a>
## Microservices : corrélation et traçage

Lorsqu'un appel HTTP devient **passerelle → service A → service B → courtier (broker) → worker**, un simple journal d'accès par service est **insuffisant**.

### Identifiant de corrélation

* Propagez un identifiant stable sur chaque appel sortant (`X-Request-Id` ou **`traceparent` du W3C** aux côtés de votre identifiant interne).
* Consignez-le dans **chaque** service à l'entrée ; incluez-le dans les charges utiles **asynchrones** (charge utile de tâche, en-têtes de messages).

### Traçage distribué

* **OpenTelemetry** est la norme émergente **indépendante des fournisseurs** pour émettre des traces ; les collecteurs les transmettent à **Jaeger**, **Tempo**, **Zipkin** ou à des backends SaaS.
* Les écosystèmes PHP varient en maturité — vérifiez l'**instrumentation** pour votre client HTTP, votre pilote de base de données et votre bibliothèque de file d'attente. Un traçage partiel vaut toujours mieux que rien du tout.

### Frontières des services

* Standardisez les politiques de **délai d'attente (timeout), de nouvelles tentatives (retry) et d'idempotence** ; l'observabilité révélera des **tentatives en cascade** si chaque couche réessaie aveuglément.

---

<a id="tools"></a>
## Éventail d'outils : du classique au moderne

### Ère de l'hôte et du réseau

* **syslog**, **rsyslog**, **logrotate** — centralisation de fichiers simples ; toujours valables en tant qu'étape de **transport**.
* **Nagios**, **Icinga**, **Zabbix** — vérifications d'hôtes, ping, disque, sondes de service simples. Moins axés sur les **traces applicatives**, toujours courants pour les **métriques d'infrastructure de base**.

### Agrégation de journaux

* **ELK / Elastic Stack** (Elasticsearch, Logstash/Beats, Kibana) — recherche puissante ; gérez l'infrastructure ou achetez de la capacité de manière consciente.
* **Graylog**, **Splunk** (entreprise) — espace de solutions similaire.

### Métriques et tableaux de bord

* Modèle de collecte de **Prometheus** + tableaux de bord **Grafana** — standard de facto pour **Kubernetes** et de nombreux parcs sur serveurs nus (bare-metal) ; **Alertmanager** pour le routage.
* **VictoriaMetrics**, **Mimir**, **Thanos** — variantes à long terme ou hautement disponibles autour des protocoles Prometheus.

### Journaux « comme des métriques »

* **Grafana Loki** — stockage de journaux basé sur des étiquettes (labels) qui s'associe naturellement avec Grafana ; souvent moins cher que d'indexer chaque champ comme le font les moteurs de recherche.

### Cloud-native

* **AWS CloudWatch**, **Google Cloud Logging/Monitoring**, **Azure Monitor** — intégration étroite si vous payez déjà ces factures.

### SaaS tout-en-un

* **Datadog**, **New Relic**, **Honeycomb** — journaux, métriques, APM, RUM ; **valeur rapide**, **tarification au volume** — attention à la cardinalité.

### Erreurs et APM pour PHP

* **Sentry** (erreurs + performance), **Scout**, **Tideways** (profilage axé sur PHP) — forte utilisation par la communauté Laravel.

### Vague de standardisation

* **OpenTelemetry (OTel)** — SDKs/exportateurs unifiés ; le **collecteur (collector)** peut distribuer les flux vers de nombreux backends. **L'adoption augmente** précisément pour éviter la dépendance vis-à-vis d'un fournisseur pour chaque signal.

---

<a id="load"></a>
## Sous charge : échantillonnage, cardinalité, coût

* Le **volume de journaux** augmente linéairement avec le trafic ; le format **JSON par requête** en mode `debug` peut monopoliser le processeur de l'application. Utilisez des **niveaux (levels)** et du **débogage échantillonné** sur les chemins critiques.
* **Étiquettes Prometheus** : n'utilisez jamais de valeurs **non limitées** (URL brutes avec identifiants, adresses e-mail) comme noms d'étiquettes ou valeurs à forte cardinalité — **les séries temporelles explosent**.
* **Échantillonnage des traces** : conservez **100 %** pour les erreurs ou les requêtes lentes ; échantillonnez le reste — vos backends et votre portefeuille vous remercieront.
* **Rétention** : définissez les données **chaudes** (jours) vs **froides** (stockage objet) vs **à supprimer** ; la conformité peut exiger une rétention plus longue des journaux d'**audit**, séparément des journaux de **débogage**.

---

<a id="alerting"></a>
## Des alertes que les humains respecteront

Alertez sur les pannes **visibles pour l'utilisateur** ou **imminentes** : consommation du budget d'erreurs (**SLO burn**), hausse du **taux d'erreur**, temps d'attente en file **p95**, seuil de **disque**, expiration de **certificat**.

Évitez de biper/notifier pour des conditions **connues comme bruyantes**, à moins d'y associer des **guides d'intervention (runbooks)**. Une « CPU > 80 % » pendant cinq minutes n'est souvent **pas** un incident ; une chute de 10 fois du **taux de réussite des paiements** en est un.

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **Étiquettes de métriques à forte cardinalité** : Ajouter des identifiants uniques comme `user_id` ou des paramètres d'`url` générés dynamiquement comme étiquettes Prometheus, ce qui entraîne l'épuisement de la mémoire.
2. **Fuite d'identifiants dans les journaux** : Consigner `$request->all()` sur les points de terminaison de connexion ou de réinitialisation de mot de passe, exposant ainsi les mots de passe, les jetons et les informations personnelles.
3. **Maintien de Telescope activé en production** : Stockage illimité en base de données des spans de l'application, ralentissant les requêtes et surchargeant le stockage principal.
4. **Absence de délai d'attente (timeout) sur les requêtes externes** : Appeler des API tierces sans délai d'attente, provoquant le blocage des processus HTTP et l'épuisement des processus enfants FPM.

---

<a id="checklist"></a>
## Liste de contrôle

1. **Journaux structurés** vers stdout ou un expéditeur ; **un seul** identifiant de corrélation pour le travail synchrone et asynchrone.
2. **Signaux dorés (golden signals)** par service : latence, trafic, erreurs, saturation (ainsi que la **profondeur de file d'attente** pour les workers Laravel).
3. **Points de terminaison de santé** qui testent les dépendances **réelles** ; séparez la **survie (liveness)** de la **disponibilité (readiness)** là où les orchestrateurs l'exigent.
4. **Suivi des erreurs** en production ; outils **de type Telescope** restreints aux environnements hors production.
5. **Examen des coûts et de la cardinalité** avant d'activer « tout journaliser » ou « tout étiqueter ».

---

## Résumé

L'observabilité fait **partie du produit** : le même code Laravel qui sert les utilisateurs doit vous dire — **avec des preuves** — quand il est sur le point d'échouer et **où** regarder en premier.

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Quel est le principal problème lié à l'ajout d'e-mails d'utilisateurs en tant qu'étiquettes dans une base de données de métriques Prometheus ?
- A) Prometheus ne prend pas en charge les valeurs de type chaîne de caractères.
- B) Cela provoque une explosion de la cardinalité des métriques, faisant planter la base de données de métriques.
- C) Cela viole les politiques standard de formatage d'URL.

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : B**
Chaque étiquette d'e-mail unique crée une nouvelle série chronologique. Si vous avez des milliers d'utilisateurs, Prometheus manquera rapidement de mémoire en raison de l'explosion de la cardinalité.
</details>

---

### Question 2 : Dans un environnement Kubernetes ou avec répartition de charge, quel est le but du test de disponibilité (readiness check) ?
- A) Vérifier si le processus du serveur a démarré.
- B) Vérifier si l'application est prête à accepter du trafic (par exemple, la connexion à la base de données est opérationnelle).
- C) Tracer les requêtes lentes.

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : B**
Une sonde de disponibilité vérifie si le conteneur est prêt à gérer le trafic web entrant. Si elle échoue, le répartiteur de charge cesse de transférer des requêtes à ce nœud.
</details>
