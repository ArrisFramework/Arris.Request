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
$active = Request('active')->asBool();      // __invoke-шорткат
```

Источником данных по умолчанию является `$_REQUEST`. Вторым аргументом можно
передать произвольный массив (например, протестировать обработчик формы):

```php
$age = Request::from('age', ['age' => '42'])->asInt();  // 42
```

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

| Метод                    | Тип      | Поведение                                                                              |
|--------------------------|----------|----------------------------------------------------------------------------------------|
| `raw()`                  | `mixed`  | сырое значение без преобразований                                                      |
| `asString()` / `asStr()` | `string` | строковое приведение                                                                   |
| `asInt()`                | `int`    | `FILTER_VALIDATE_INT`, иначе дефолт                                                    |
| `asFloat()`              | `float`  | `FILTER_VALIDATE_FLOAT`, иначе дефолт                                                  |
| `asBool()`               | `bool`   | `true/1/on/yes` → true; `false/0/off/no/''` → false                                    |
| `asCheckbox()`           | `int`    | `asBool() ? 1 : 0` — для INT-колонки БД                                                |
| `asArray()`              | `array`  | как есть, иначе `(array)$default`; пустой `[]` → default (если не `allowEmptyArray()`) |
| `asEmail()`              | `string` | валидация `FILTER_VALIDATE_EMAIL`, иначе default                                       |
| `asUrl()`                | `string` | валидация `FILTER_VALIDATE_URL`, иначе default                                         |
| `asText()`               | `string` | очистка текста: `strip_tags` + `htmlspecialchars`, удаление пустых `<div>/<p>`, схлопывание пробелов |

Примеры:

```php
// Чекбокс в БД
$active = Request::from('active')->asCheckbox();  // 0 или 1

// Пустой массив из формы -> дефолт
$tags = Request::from('tags')->default(['нет'])
    ->allowEmptyArray(false)   // пустой [] считается отсутствующим
    ->asArray();

// Вложенные массивы возвращаются как есть
$dishes = Request::from('dishes', $_POST)->asArray();

// Санитизация текста (стриптит теги и экранирует спецсимволы)
$text = Request::from('content')->asText();

// С сохранением HTML (например, для WYSIWYG-редактора)
$html = Request::from('content')->allowHtml()->asText();

// Отключить удаление пустых <p>/<div>
$raw = Request::from('content')->allowHtml()->noEmptyContent(false)->asText();
```

### __invoke-шорткат

Инстанс можно переиспользовать для чтения разных полей из одного источника:

```php
$r = Request::from('data', $input);
$title  = $r('title')->trim()->asString();
$price  = $r('price')->asFloat();
$active = $r('active')->asCheckbox();
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