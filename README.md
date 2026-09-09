# Arris.Request

Fluent-reader данных запроса (REQUEST) для экосистемы Arris µFramework. Включает:

- класс `Arris\Request` — чтение поля из источника с чейнингом преобразований и приведением типов;
- класс `Arris\Request\Dataset` — маппинг массива по правилам с приведением типов.

Standalone-пакет.

## Requirements

- PHP 8.2+
- ext-mbstring

## Installation

```sh
$ composer require karelwintersky/arris.request
```

## Usage

### Минимальная форма

```php
use Arris\Request;

// Одна строка — строка из $_REQUEST
$email = Request::str('email');

// Эквиваленты
$age   = Request::from('age')->asInt();     // int из $_REQUEST
$age   = (new Request('age'))->asInt();     // то же самое

// __invoke-шорткат: сначала import() создаёт «пульт» (источник без поля),
// затем $r('field') читает конкретное поле из того же источника
$r      = Request::import($_POST);
$active = $r('active')->asBool();
```

Источником данных по умолчанию является `$_REQUEST`. Вторым аргументом можно
передать произвольный массив (например, протестировать обработчик формы):

```php
$age = Request::from('age', ['age' => '42'])->asInt();  // 42
```

### import() — «пульт» для __invoke

`Request::import($source = null)` связывает источник (по умолчанию `$_REQUEST`) без
конкретного поля и возвращает **source-reader**: его `raw()`, `asArray()` и `asJson()`
отдают весь источник целиком. Каждый вызов `$r('field')` создаёт обычный читатель
поля из того же источника:

```php
$r = Request::import($_POST);

$quote = $r('tavern_chef_quote')->apply('strip_tags')->trim()->asStr();
$name  = $r('name')->asString();

$whole = $r->raw();            // весь $_POST (массив)
$json  = $r->asJson();         // весь $_POST как JSON
```

Внимание: вызывать класс как функцию (`Request('name')`) нельзя — `__invoke`
работает только на экземпляре.

### Чейнинг преобразований

Step-методы возвращают `$this` и комбинируются в любом порядке.

```php
$name = Request::from('name')
    ->trim()                                  // убрать пробелы
    ->maxLength(50)                           // обрезать до 50 символов
    ->default('нет данных')
    ->apply(fn($v) => mb_strtolower($v))      // свой коллбэк
    ->asString();                             // финальное приведение
```

`apply()` принимает любой callable: замыкание, строку-функцию (`'strtolower'`), массив `[Class::class, 'method']` и т.д.

Встроенные шорткаты для частых преобразований:

```php
// strip_tags — быстрая замена apply('strip_tags')
$clean = Request::from('content')->stripTags()->asString();

// substr — вырезать фрагмент. ВСЕГДА многобайтовый (mb_substr),
// работает с символами языка, а не байтами
$short = Request::from('name')
    ->trim()
    ->substr(1, 3)          // mb_substr($value, 1, 3)
    ->asString();

$rest = Request::from('name')->substr(2)->asString();  // до конца строки
```

### Замыкающие методы (возвращают значение)

| Метод                    | Тип      | Поведение                                                                                                              |
|--------------------------|----------|------------------------------------------------------------------------------------------------------------------------|
| `raw()`                  | `mixed`  | сырое значение без преобразований; для `import()` — весь источник                                                      |
| `asString()` / `asStr()` | `string` | строковое приведение                                                                                                   |
| `asInt()`                | `int`    | `FILTER_VALIDATE_INT`, иначе дефолт                                                                                    |
| `asFloat()`              | `float`  | `FILTER_VALIDATE_FLOAT`, иначе дефолт                                                                                  |
| `asBool()`               | `bool`   | `true/1/on/yes` → true; `false/0/off/no/''` → false                                                                    |
| `asCheckbox()`           | `int`    | `asBool() ? 1 : 0` — для INT-колонки БД                                                                                |
| `asArray()`              | `array`  | как есть, иначе `(array)$default`; пустой `[]` → default (если не `allowEmptyArray()`); для `import()` — весь источник |
| `asEmail()`              | `string` | валидация `FILTER_VALIDATE_EMAIL`, иначе default                                                                       |
| `asUrl()`                | `string` | валидация `FILTER_VALIDATE_URL`, иначе default                                                                         |
| `asText()`               | `string` | очистка текста: `strip_tags` + `htmlspecialchars`, удаление пустых `<div>/<p>`, схлопывание пробелов                   |
| `asJson()`               | `string` | JSON результата (`Dataset::jsonize`): весь источник для `import()`                                                     |

Примеры:

```php
// Чекбокс в БД
$active = Request::from('active')->asCheckbox();  // 0 или 1

// Весь payload как JSON (логгирование, подпись вебхука, отладка)
$json = Request::import($_POST)->asJson();

// Значение поля как JSON (например, поле-массив)
$tags = Request::from('tags', $_POST)->asJson();

// Пустой массив из формы -> дефолт
$tags = Request::from('tags')->default(['нет'])
    ->allowEmptyArray(false)   // пустой [] считается отсутствующим
    ->asArray();

// Вложенные массивы возвращаются как есть
$dishes = Request::from('dishes', $_POST)->asArray();

// Санитизация текста (удаляет теги и экранирует спецсимволы)
$text = Request::from('content')->asText();

// С сохранением HTML (например, для WYSIWYG-редактора)
$html = Request::from('content')->allowHtml()->asText();

// Отключить удаление пустых <p>/<div>
$raw = Request::from('content')->allowHtml()->noEmptyContent(false)->asText();
```

### Dataset — маппинг payload по правилам

`Arris\Request\Dataset::map()` строит новый массив из исходного по набору правил
с приведением типов. Правило — ассоциативный массив с ключами:

- `source` — поле исходного массива (по умолчанию — ключ правила);
- `target` — поле результата (по умолчанию — ключ правила);
- `default` — значение, если поле отсутствует в источнике;
- `processor` — фиксированное значение ИЛИ callable `fn($value, $source)`;
- `type` — приведение типа: `'int'`, `'float'`, `'bool'`, `'string'`, `'array'`.

В результат попадают **только** ключи правил. Falsy-значения (`0`, `''`, `false`,
`null`) не считаются отсутствующими.

```php
use Arris\Request;
use Arris\Request\Dataset;

$payload = Request::from('data', $_POST)->asArray();

$clean = Dataset::map($payload, [
    'name'  => ['type' => 'string'],
    'age'   => ['type' => 'int'],
    'email' => [
        'processor' => fn($v) => strtolower(trim($v)),
    ],
    'price_with_tax' => [
        'source'    => 'price',
        'processor' => fn($v) => $v * 1.2,
        'type'      => 'float',
    ],
    'active' => [
        'type'    => 'bool',
        'default' => false,
    ],
]);
```

Есть также `Dataset::castToType($value, $type)` и `Dataset::jsonize($data, $flags)`.

## Testing

```sh
$ composer install
$ vendor/bin/phpunit
```

## License

The MIT License (MIT).