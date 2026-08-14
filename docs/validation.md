# Validation

Tondbād provides a unified validation layer inspired by Zod, Valibot, and Pydantic:

- **Zod-style** fluent `Schema` builder with `safeParse()` / `parse()`.
- **Valibot-style** modular actions and chainable rules.
- **Pydantic-style** `#[Field]` attributes for typed read-only DTOs and `FormRequest` classes.

The legacy string-rule API (`$request->validate([...])`) remains fully supported.

## Schema validation

```php
use TondbadSwoole\Validation\Schema;

$loginSchema = Schema::object([
    'email' => Schema::string()->email()->required(),
    'password' => Schema::string()->min(8)->required(),
    'age' => Schema::int()->coerce()->gte(18)->nullable()->default(null),
    'tags' => Schema::array(Schema::string())->max(10)->default([]),
])->lax();

$result = $loginSchema->safeParse($data);

if (!$result->valid) {
    // $result->errors is a list of {field, rule, message, params}
    return $response->json($result->errors, 422);
}

$clean = $result->data;
```

`Schema` is **strict by default**. Use `->lax()` or `->coerce()` when validating HTTP input (which is always strings). `parse()` throws `ValidationException`; `safeParse()` returns a `ValidationResult`.

### Available schema types

| Factory | Description |
|---|---|
| `Schema::string()` | String values |
| `Schema::int()` | Integers |
| `Schema::float()` | Floats |
| `Schema::bool()` | Booleans |
| `Schema::array($itemSchema)` | Arrays of `$itemSchema` |
| `Schema::object($fields)` | Nested object schemas |
| `Schema::enum(...$values)` | Union values |
| `Schema::literal($value)` | Exact value |
| `Schema::json()` | Decodes a JSON string |
| `Schema::mixed()` | Any value |

### Chainable methods

- `required()`, `optional()`, `nullable()`, `sometimes()`
- `default($value)`, `bail()`, `alias($name)`, `messages([...])`
- `strict()`, `lax()`, `coerce()`
- `min($n)`, `max($n)`, `gt($n)`, `gte($n)`, `lt($n)`, `lte($n)`
- `email()`, `url()`, `uuid()`, `ip()`
- `regex($pattern)`, `in(...$values)`, `notIn(...$values)`
- `confirmed()`, `unique($table, $column)`, `exists($table, $column)`
- `transform($callable)`, `refine($callable, $message)`
- `pipe(ValidationAction $action)`

### Request schema validation

```php
public function store(Request $request, Response $response): void
{
    $data = $request->validateSchema($loginSchema);

    $response->json($data);
}
```

### Route parameter schemas

```php
use TondbadSwoole\Validation\Schema;

$route->get('/users/{id}', [UserController::class, 'show'])
    ->whereSchema('id', Schema::int()->gte(1));
```

Invalid route parameters now return a `404` response instead of being passed to the handler.

## DTO attributes (Pydantic-style)

```php
use TondbadSwoole\Validation\Attributes\Field;

final class LoginDto
{
    public function __construct(
        #[Field(alias: 'email_address', transform: 'trim|strtolower', rules: 'email')]
        public readonly string $email,

        #[Field(rules: 'min:8')]
        public readonly string $password,

        #[Field(default: 18, rules: 'gte:0')]
        public readonly int $age,
    ) {}
}
```

```php
use TondbadSwoole\Validation\DtoFactory;

$dto = DtoFactory::make(LoginDto::class, $request->all());
```

`#[Field]` supports:

- `alias`: input key mapping
- `rules`: pipe-separated validation rules applied to the schema
- `default`: fallback value
- `nested`: class name for nested DTOs
- `transform`: pipe-separated callable transforms (`trim`, `strtolower`, etc.)

Nested DTOs are resolved recursively. `DtoFactory` automatically coerces HTTP strings and applies defaults.

## Form requests with attributes

`FormRequest` still supports `rules()`. If `rules()` returns an empty array, it falls back to `#[Field]` attributes on the request properties.

```php
use TondbadSwoole\Http\FormRequest;
use TondbadSwoole\Validation\Attributes\Field;

class StoreUserRequest extends FormRequest
{
    #[Field(alias: 'email_address', transform: 'trim|strtolower', rules: 'email')]
    public readonly string $email;

    #[Field(rules: 'min:8')]
    public readonly string $password;

    #[Field(default: 18, rules: 'gte:0')]
    public readonly int $age;
}
```

```php
public function store(StoreUserRequest $request, Response $response): void
{
    $response->json([
        'email' => $request->email,
        'age' => $request->age,
    ]);
}
```

## Legacy string rules

The existing string-rule engine is unchanged:

```php
$data = $request->validate([
    'email' => 'required|email',
    'password' => 'required|min:8|confirmed',
    'age' => 'nullable|integer|min:18',
]);
```

## Validation in configuration and environment

```php
use TondbadSwoole\Validation\Schema;

$port = config()->validate('app.http.port', Schema::int()->gte(1)->lte(65535));

$workers = env()->getInt('app.http.worker_num', 4);
$debug = env()->getBool('app.debug', false);
```

## Built-in string rules

| Rule | Description |
|---|---|
| `required` | Field must be present and not empty |
| `nullable` | Allows null/empty values; other rules skip null |
| `sometimes` | Validates only if field is present |
| `bail` | Stops on first failure |
| `email` | Valid email address |
| `string` | Value must be a string |
| `int` / `integer` / `numeric` | Numeric value |
| `min:n` / `max:n` | Length or numeric minimum/maximum |
| `confirmed` | Field `{name}_confirmation` must match |
| `same:field` | Must equal another field |
| `different:field` | Must differ from another field |
| `in:a,b,c` | Must be one of the listed values |
| `not_in:a,b,c` | Must not be any of the listed values |
| `json` | Valid JSON string |
| `uuid` | Valid UUID string |
| `unique:table,column` | Column value must not exist in the database |
| `exists:table,column` | Column value must exist in the database |
| `alpha` / `alpha_num` / `alpha_dash` | Character-only strings |
| `array` | Value must be an array |
| `boolean` | Boolean-ish value |
| `date` / `date_format:Y-m-d` | Date validation |
| `digits:n` / `digits_between:min,max` | Exact or range digit count |
| `ip` | Valid IP address |
| `regex:pattern` | Matches the given regex |
| `url` | Valid URL |
| `after:date` / `before:date` | Date comparisons |

## Custom rules

Implement `Rule`:

```php
<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class PhoneRule implements Rule
{
    public function getName(): string
    {
        return 'phone';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_string($value) && preg_match('/^\+?[1-9]\d{1,14}$/', $value) === 1;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute field is not a valid phone number.';
    }
}
```

Register the rule on a validator instance:

```php
use TondbadSwoole\Validation\Validator;

$validator = new Validator($request->all(), ['phone' => 'phone']);
$validator->extend('phone', new PhoneRule());

$data = $validator->validated();
```

## Custom error messages

```php
$data = $request->validate(
    ['email' => 'required|email'],
    ['email.required' => 'Please provide an email address.']
);
```

## Form requests (legacy rules)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use TondbadSwoole\Http\FormRequest;

class LegacyStoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ];
    }
}
```

```php
public function store(LegacyStoreUserRequest $request, Response $response): void
{
    $data = $request->validated();

    $response->json($data);
}
```

`FormRequest` validates on construction and throws `ValidationException` when rules fail. Override `authorize()` to reject requests before validation runs.

## Validation rules format

Rules can be a pipe-separated string or an array:

```php
['email' => ['required', 'email']]
```

## Benchmarks

A `benchmarks/validation.php` script compares the new `Schema` parser with the legacy string-rule `Validator`:

```bash
php benchmarks/validation.php
```

Sample output (10,000 iterations):

```
Schema (safeParse):   0.1300 s (13.00 μs/iter)
Legacy Validator:    0.1100 s (11.00 μs/iter)
```

Both engines are in the same microsecond range. The schema engine trades a small amount of raw speed for richer type safety and structured errors.
