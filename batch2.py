# -*- coding: utf-8 -*-
import json
import subprocess

questions = [
  {
    "question_text": "Was ist die Ausgabe des folgenden Code-Snippets?\n\n```php\n$x = 'y';\n$y = 'z';\necho $$x;\n```",
    "options": [
      "y",
      "z",
      "x",
      "Eine Notice wegen einer nicht definierten Variable wird ausgelöst."
    ],
    "correct_answer_index": 1,
    "explanation": "Dies ist eine variable Variable (variable variable). `$$x` wird als `$y` ausgewertet, weil `$x` den String 'y' enthält. Da `$y` den Wert 'z' enthält, wird 'z' ausgegeben.",
    "points": 10
  },
  {
    "question_text": "Wie verhält sich die statische Variable `$count` im folgenden Code-Snippet und wie lange existiert sie?\n\n```php\nfunction counter() {\n    static $count = 0;\n    $count++;\n    return $count;\n}\n```",
    "options": [
      "Sie wird bei jedem Funktionsaufruf initialisiert und zerstört, wenn die Funktion zurückkehrt.",
      "Sie behält ihren Wert über mehrere Funktionsaufrufe hinweg und wird nur einmal initialisiert.",
      "Sie wird global über alle Funktionen und Klassen im Ausführungs-Runtime hinweg geteilt.",
      "Sie führt zu einem Syntaxfehler, es sei denn, die Funktion ist eine statische Klassenmethode."
    ],
    "correct_answer_index": 1,
    "explanation": "Statische Variablen innerhalb von Funktionen existieren nur im lokalen Gültigkeitsbereich der Funktion, behalten jedoch ihren Wert zwischen den Ausführungsaufrufen. Der Initialisierungsausdruck wird nur einmal beim ersten Aufruf ausgeführt.",
    "points": 10
  },
  {
    "question_text": "Wie kann die Funktion `printGlobal()` im folgenden Code ohne Verwendung des Schlüsselworts `global` auf die Variable `$globalVar` zugreifen?\n\n```php\n$globalVar = \"Hello World\";\nfunction printGlobal() {\n    // Access $globalVar here\n}\n```",
    "options": [
      "Durch Verwendung des superglobalen Arrays `$GLOBALS`.",
      "Durch Voranstellen eines Unterstrichs vor den Variablennamen.",
      "Durch Importieren der Variable mit dem Schlüsselwort `import`.",
      "Es ist nicht möglich, auf globale Variablen ohne das Schlüsselwort `global` zuzugreifen."
    ],
    "correct_answer_index": 0,
    "explanation": "Globale Variablen können überall im Skript über das superglobale assoziative Array `$GLOBALS` aufgerufen werden, z. B. `$GLOBALS['varName']`.",
    "points": 10
  },
  {
    "question_text": "Wie unterscheidet sich das Vergleichsverhalten der beiden folgenden Codeblöcke? (PHP 8.0+)\n\n```php\n// Block A (switch)\nswitch ($value) {\n    case '1': echo 'string'; break;\n}\n\n// Block B (match)\nmatch ($value) {\n    '1' => 'string',\n};\n```",
    "options": [
      "Der `match`-Ausdruck verwendet einen strikten Vergleich (`===`), während `switch` einen losen Vergleich (`==`) verwendet.",
      "Der `match`-Ausdruck verwendet einen losen Vergleich (`==`), während `switch` einen strikten Vergleich (`===`) verwendet.",
      "Beide verwenden den losen Vergleich, aber `match` gibt einen Wert zurück.",
      "Beide verwenden den strikten Vergleich, aber `match` erfordert `break`-Anweisungen."
    ],
    "correct_answer_index": 0,
    "explanation": "Der `match`-Ausdruck führt einen strikten Identitätsvergleich (`===`) durch und gibt einen Wert zurück, während die `switch`-Anweisung einen losen Gleichheitsvergleich (`==`) verwendet. Zudem gibt es bei `match` kein Durchlaufen (Fall-through) und es erfordert kein `break`.",
    "points": 10
  },
  {
    "question_text": "Was ist ein gefährlicher Seiteneffekt bei der Verwendung einer Referenz in der ersten Schleife dieses Codes?\n\n```php\n$array = [1, 2, 3];\nforeach ($array as &$value) {\n    // do nothing\n}\n// $value is not unset\n```",
    "options": [
      "Das Array wird in ein schreibgeschütztes Objekt umgewandelt.",
      "Das letzte Element des Arrays bleibt referenziert; eine spätere Änderung von `$value` überschreibt das letzte Element.",
      "Die nächste Schleife über ein beliebiges Array löst eine Referenz-Rekursions-Exception aus.",
      "Es verursacht ein Speicherleck, da die Garbage Collection von PHP referenzierte Variablen nicht freigeben kann."
    ],
    "correct_answer_index": 1,
    "explanation": "Wenn `foreach` endet, bleibt die Variable `$value` als Referenz auf das letzte Element des Arrays bestehen. Wenn `$value` später im Skript geändert wird (z. B. in einer anderen Schleife), wird das letzte Element des Arrays unerwartet aktualisiert. Es entspricht der Best Practice, `unset($value)` direkt nach der Schleife aufzurufen.",
    "points": 10
  },
  {
    "question_text": "Wie unterscheidet sich das Fehlerverhalten der beiden folgenden Anweisungen, wenn die Datei `missing.php` nicht existiert?\n\n```php\n// Statement A\ninclude 'missing.php';\n\n// Statement B\nrequire 'missing.php';\n```",
    "options": [
      "`include` gibt eine Warnung aus und stoppt die Ausführung; `require` gibt eine Warnung aus und fährt fort.",
      "`include` gibt eine Warnung aus und fährt fort; `require` löst einen fatalen Fehler aus und stoppt die Ausführung sofort.",
      "Beide stoppen die Skriptausführung, aber `require` protokolliert den Fehler in einer Datei.",
      "`include` wird für PHP-Dateien verwendet; `require` wird nur für Konfigurationsdateien verwendet."
    ],
    "correct_answer_index": 1,
    "explanation": "`include` erzeugt ein `E_WARNING`, erlaubt dem Skript jedoch, weiterzulaufen. `require` erzeugt ein `E_COMPILE_ERROR` (fataler Fehler) und stoppt die Skriptausführung sofort.",
    "points": 10
  },
  {
    "question_text": "Warum ist ein Sprung wie der in Block B aufgrund der Einschränkungen der `goto`-Anweisung in PHP ungültig?\n\n```php\n// Block A (Valid)\ngoto end;\nend:\n\n// Block B (Invalid)\ngoto loop_body;\nfor ($i = 0; $i < 10; $i++) {\n    loop_body:\n}\n```",
    "options": [
      "Sie kann nicht in Schleifen oder `switch`-Anweisungen hineinspringen.",
      "Sie kann nur zu Zeilen springen, die mit einer Nummer markiert sind.",
      "Sie kann zwischen verschiedenen Dateien springen.",
      "Sie kann nicht innerhalb von Funktionen verwendet werden."
    ],
    "correct_answer_index": 0,
    "explanation": "Der `goto`-Operator hat Einschränkungen: Das Ziel-Label muss sich in derselben Datei und in derselben Funktion/Methode befinden. Man kann nicht in eine Schleife, eine `switch`-Anweisung oder eine Funktion hineinspringen, obwohl man aus ihnen herausspringen kann.",
    "points": 10
  },
  {
    "question_text": "Was gibt der Spaceship-Operator (`<=>`) zurück, wenn `$a` und `$b` in diesem Ausdruck verglichen werden? (PHP 7.0+)\n\n```php\n$result = $a <=> $b;\n```",
    "options": [
      "`true`, wenn `$a` größer ist, andernfalls `false`.",
      "`-1`, wenn `$a < $b`, `0`, wenn `$a == $b`, und `1`, wenn `$a > $b`.",
      "Die Differenz zwischen den beiden Werten als Float.",
      "Ein Boolean, der angibt, ob die beiden Operanden vom gleichen Typ sind."
    ],
    "correct_answer_index": 1,
    "explanation": "Der Spaceship-Operator `<=>` gibt `-1` zurück, wenn der linke Operand kleiner als der rechte ist, `0`, wenn sie gleich sind, und `1`, wenn der linke Operand größer ist.",
    "points": 10
  },
  {
    "question_text": "Wie unterscheiden sich die beiden folgenden Arten der Konstantendefinition unter der Haube?\n\n```php\n// Method A\nconst MAX_LIMIT = 100;\n\n// Method B\ndefine('MIN_LIMIT', 10);\n```",
    "options": [
      "`const`-Konstanten werden zur Kompilierzeit definiert und können nicht innerhalb von Kontrollstrukturen (wie `if`) platziert werden, wohingegen `define()`-Konstanten zur Laufzeit definiert werden.",
      "`const`-Konstanten können neu definiert werden, während `define()`-Konstanten dauerhaft sind.",
      "`const` wird nur für Klassen verwendet, während `define()` nur global verwendet werden kann.",
      "Es gibt keinen Unterschied; sie sind absolut identisch."
    ],
    "correct_answer_index": 0,
    "explanation": "Das Schlüsselwort `const` definiert Konstanten zur Kompilierzeit, was bedeutet, dass sie nicht innerhalb von Blöcken wie `if`, Schleifen oder `try-catch` deklariert werden können. `define()` definiert Konstanten zur Laufzeit und kann überall verwendet werden.",
    "points": 10
  },
  {
    "question_text": "Was ist die Ausgabe des folgenden Code-Snippets ohne `break`-Anweisungen?\n\n```php\n$value = 1;\nswitch ($value) {\n    case 1:\n        echo \"One \";\n    case 2:\n        echo \"Two \";\n    default:\n        echo \"Default\";\n}\n```",
    "options": [
      "PHP gibt einen Syntaxfehler aus.",
      "Die `switch`-Anweisung wird nach dem Ausführen des ersten passenden Falls automatisch beendet.",
      "Die Ausführung läuft in die nächsten Case-Blöcke durch (Fall-through) und gibt 'One Two Default' aus.",
      "PHP beendet das Skript mit einer Warnung."
    ],
    "correct_answer_index": 2,
    "explanation": "Ohne eine `break`-Anweisung wird die Ausführung in den folgenden Abschnitten fortgesetzt (Fall-through), selbst wenn deren Bedingungen nicht zutreffen, bis ein `break` oder das Ende von `switch` erreicht wird.",
    "points": 10
  },
  {
    "question_text": "Was passiert, wenn der folgende `match`-Ausdruck ausgeführt wird? (PHP 8.0+)\n\n```php\n$value = 3;\n$result = match ($value) {\n    1 => 'One',\n    2 => 'Two',\n};\n```",
    "options": [
      "Es wird null zurückgegeben.",
      "Es wird ein `UnhandledMatchError` ausgelöst.",
      "Das Skript wird mit einem fatalen Syntaxfehler beendet.",
      "Die Ausführung geht in den nächsten Funktionsblock über (Fall-through)."
    ],
    "correct_answer_index": 1,
    "explanation": "Ein `match`-Ausdruck muss vollständig sein (exhaustive). Wenn keine Bedingung zutrifft und kein Standardzweig (default arm) angegeben ist, löst PHP eine `UnhandledMatchError`-Exception aus.",
    "points": 10
  },
  {
    "question_text": "Wie verhält es sich, wenn im untenstehenden Code nach dem Verlassen der Schleife auf `$result` zugegriffen wird?\n\n```php\nfor ($i = 0; $i < 3; $i++) {\n    $result = $i * 2;\n}\necho $result;\n```",
    "options": [
      "Nein, in einer Schleife definierte Variablen sind block-scoped und werden beim Verlassen der Schleife zerstört, weshalb dies zu einem fatalen Fehler führt.",
      "Ja, PHP hat keinen Block-Scope für Schleifen; die Variable bleibt zugänglich und es wird 4 ausgegeben.",
      "Nur, wenn sie mit dem Schlüsselwort `public` deklariert wurden.",
      "Nur, wenn die Schleife eine `foreach`-Schleife ist."
    ],
    "correct_answer_index": 1,
    "explanation": "PHP besitzt keinen Block-Scope für Kontrollstrukturen wie Schleifen (`for`, `foreach`, `while`) oder bedingte Anweisungen (`if`). Darin definierte Variablen bleiben außerhalb der Struktur innerhalb derselben Funktion oder des globalen Bereichs zugänglich.",
    "points": 10
  },
  {
    "question_text": "Welchen Gültigkeitsbereich (scope) hat die Variable `$innerVar`, die in der verschachtelten Funktion definiert ist?\n\n```php\nfunction outer() {\n    function inner() {\n        $innerVar = \"nested\";\n    }\n}\n```",
    "options": [
      "Sie ist lokal für die verschachtelte Funktion `inner()` und die äußere Funktion `outer()` kann nicht darauf zugreifen.",
      "Sie wird automatisch mit dem Gültigkeitsbereich der äußeren Funktion geteilt.",
      "Sie wird automatisch global.",
      "PHP unterstützt keine Deklaration von verschachtelten Funktionen."
    ],
    "correct_answer_index": 0,
    "explanation": "Selbst wenn eine verschachtelte Funktion innerhalb einer anderen Funktion definiert wird, besitzt sie ihren eigenen lokalen Gültigkeitsbereich. Variablen innerhalb der verschachtelten Funktion sind für die äußere Funktion nicht zugänglich.",
    "points": 10
  },
  {
    "question_text": "Was passiert, wenn Sie `declare(ticks=1);` in einem PHP-Skript verwenden?",
    "options": [
      "Es misst die Ausführungszeit jedes Statements in Ticks.",
      "Es registriert einen Event-Listener, der nach einer bestimmten Anzahl von Ausführungsticken von Low-Level-Anweisungen einen Callback ausführt.",
      "Es zwingt das Skript, zwischen den Anweisungen für 1 Millisekunde zu pausieren (sleep).",
      "Es deaktiviert die Garbage Collection."
    ],
    "correct_answer_index": 1,
    "explanation": "Die `ticks`-Direktive weist den Parser an, nach allen N Low-Level-Anweisungen ein Event auszulösen. Diese Events können mit `register_tick_function()` abgefangen werden, beispielsweise für Profiling oder Signal-Handling.",
    "points": 10
  },
  {
    "question_text": "Was ist die Ausgabe des folgenden Codes?\n\n```php\n$a = 1;\nfunction test() {\n    echo $a;\n}\ntest();\n```",
    "options": [
      "1",
      "Eine Warnung/Notice bezüglich einer nicht definierten Variable `$a` und es wird kein Wert ausgegeben (oder null).",
      "Ein fataler Fehler (Fatal Error), da `$a` nicht innerhalb von `test()` deklariert ist.",
      "0"
    ],
    "correct_answer_index": 1,
    "explanation": "In PHP befinden sich Variablen, die außerhalb von Funktionen definiert wurden, im globalen Gültigkeitsbereich und sind nicht automatisch innerhalb von Funktionen zugänglich. Der Versuch, auf `$a` ohne das Schlüsselwort `global` oder das Array `$GLOBALS` zuzugreifen, führt zu einer Warnung wegen einer nicht definierten Variable.",
    "points": 10
  }
]

with open('batch2.json', 'w', encoding='utf-8') as f:
    json.dump(questions, f, ensure_ascii=False, indent=2)

subprocess.run(['python', 'translate_helper.py', 'batch2.json'], check=True)
print("Batch 2 completed successfully.")
